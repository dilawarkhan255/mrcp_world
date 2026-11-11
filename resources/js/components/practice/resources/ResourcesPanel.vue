<script setup lang="ts">
import ExternalLinkItem from '@/components/practice/resources/ExternalLinkItem.vue';
import ResourceCard from '@/components/practice/resources/ResourceCard.vue';
import type { QuestionResult } from '@/types/practice';
import { Link } from '@inertiajs/vue3';
import { BookOpen, FileText, Image as ImageIcon, Lock, Search, Youtube } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * The learning resources of the current MCQ, in five sections: Extended
 * Textbook, Image Atlas, References & guidelines, Image links and YouTube.
 * Everything comes from the question's result, which the server only sends
 * once the question is answered (or the mock is closed), because a chapter,
 * an image or a reference can give the answer away. Before that, each
 * section says what will appear.
 */
const props = defineProps<{ result?: QuestionResult | null; lockedText?: string }>();

const resources = computed(() => props.result?.resources ?? null);
const locked = computed(() => !props.result);
const lockMessage = computed(() => props.lockedText ?? 'Answer the question to see these resources.');
const textbook = computed(() => resources.value?.textbook.items ?? []);
const atlas = computed(() => resources.value?.atlas.items ?? []);
const references = computed(() => props.result?.references ?? []);
/** Curated image links first, then the live searches (Google Images, Radiopaedia, DermNet…). */
const imageLinks = computed(() => {
    const links: { url: string; label: string; hint: string | null }[] = (props.result?.image_links ?? []).map((link) => ({
        url: link.url,
        label: link.label,
        hint: null,
    }));
    const term = resources.value?.web.term ?? props.result?.image_search ?? null;
    if (resources.value?.web.google_images) links.push({ url: resources.value.web.google_images, label: 'Google Images', hint: term });
    for (const search of props.result?.image_searches ?? []) links.push({ url: search.url, label: search.label, hint: term });
    return links;
});
const videoUrl = computed(() => (props.result?.youtube_id ? 'https://www.youtube.com/watch?v=' + props.result.youtube_id : null));
const videoSearch = computed(() => resources.value?.web.youtube ?? null);
</script>

<template>
    <div class="space-y-4" aria-label="Learning resources for this question" role="region">
        <ResourceCard title="Extended Textbook" :icon="BookOpen" :count="textbook.length || undefined">
            <p v-if="locked" class="flex items-center gap-2 text-xs text-muted-foreground">
                <Lock class="size-3.5" aria-hidden="true" />{{ lockMessage }}
            </p>
            <template v-else>
                <p v-if="textbook.length && !resources?.textbook.linked && resources?.textbook.subject" class="mb-1 text-xs text-muted-foreground">
                    From the {{ resources.textbook.subject }} chapters
                </p>
                <ul v-if="textbook.length" class="-mx-2 space-y-0.5">
                    <li v-for="chapter in textbook" :key="chapter.url">
                        <Link :href="chapter.url" class="block rounded-md px-2 py-1.5 transition-colors hover:bg-muted">
                            <span class="block font-medium leading-snug hover:text-primary">{{ chapter.title }}</span>
                            <span v-if="chapter.summary" class="line-clamp-2 block text-xs text-muted-foreground">{{ chapter.summary }}</span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-xs text-muted-foreground">No chapter on this topic yet.</p>
            </template>
            <Link href="/textbooks" class="mt-3 inline-flex text-xs font-semibold text-primary hover:underline">Open the Extended Textbook →</Link>
        </ResourceCard>

        <ResourceCard title="Image Atlas" :icon="ImageIcon" :count="atlas.length || undefined">
            <p v-if="locked" class="flex items-center gap-2 text-xs text-muted-foreground">
                <Lock class="size-3.5" aria-hidden="true" />{{ lockMessage }}
            </p>
            <template v-else>
                <ul v-if="atlas.length" class="grid grid-cols-3 gap-2">
                    <li v-for="image in atlas" :key="image.id">
                        <Link :href="image.url" class="group block" :title="image.diagnosis ? image.title + ' — ' + image.diagnosis : image.title">
                            <img
                                :src="image.image_url"
                                :alt="image.title"
                                loading="lazy"
                                class="aspect-square w-full rounded-md border object-cover transition group-hover:opacity-90 group-hover:ring-2 group-hover:ring-amber"
                            />
                            <span class="mt-1 line-clamp-1 block text-[11px] text-muted-foreground group-hover:text-foreground">{{
                                image.title
                            }}</span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-xs text-muted-foreground">No atlas image for this question yet.</p>
            </template>
            <Link href="/image-atlas" class="mt-3 inline-flex text-xs font-semibold text-primary hover:underline">Open the Image Atlas →</Link>
        </ResourceCard>

        <ResourceCard title="References & guidelines" :icon="FileText" :count="references.length || undefined">
            <p v-if="locked" class="flex items-center gap-2 text-xs text-muted-foreground">
                <Lock class="size-3.5" aria-hidden="true" />{{ lockMessage }}
            </p>
            <ol v-else-if="references.length" class="space-y-2">
                <li v-for="(reference, index) in references" :key="index" class="flex gap-2">
                    <span
                        class="h-fit shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                        :class="
                            reference.source === 'NICE'
                                ? 'bg-primary text-primary-foreground'
                                : reference.source === 'WHO'
                                  ? 'bg-[#1bb8d6] text-white'
                                  : 'bg-muted text-muted-foreground'
                        "
                        >{{ reference.source }}</span
                    >
                    <span class="min-w-0 leading-snug">
                        <a
                            v-if="reference.url"
                            :href="reference.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-primary underline-offset-2 hover:underline"
                            >{{ reference.title }}<span class="sr-only"> (opens in a new tab)</span></a
                        ><span v-else>{{ reference.title }}</span>
                        <span v-if="reference.year" class="text-muted-foreground"> ({{ reference.year }})</span>
                    </span>
                </li>
            </ol>
            <p v-else class="text-xs text-muted-foreground">No reference has been added to this question yet.</p>
        </ResourceCard>

        <ResourceCard title="Image links" :icon="Search" :count="imageLinks.length || undefined">
            <p v-if="locked" class="flex items-center gap-2 text-xs text-muted-foreground">
                <Lock class="size-3.5" aria-hidden="true" />{{ lockMessage }}
            </p>
            <ul v-else-if="imageLinks.length" class="-mx-2 space-y-0.5">
                <li v-for="link in imageLinks" :key="link.url"><ExternalLinkItem :href="link.url" :label="link.label" :hint="link.hint" /></li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">No image links for this question yet.</p>
        </ResourceCard>

        <ResourceCard title="YouTube videos" :icon="Youtube" :count="(videoUrl ? 1 : 0) + (videoSearch ? 1 : 0) || undefined">
            <p v-if="locked" class="flex items-center gap-2 text-xs text-muted-foreground">
                <Lock class="size-3.5" aria-hidden="true" />{{ lockMessage }}
            </p>
            <ul v-else-if="videoUrl || videoSearch" class="-mx-2 space-y-0.5">
                <li v-if="videoUrl"><ExternalLinkItem :href="videoUrl" label="Watch the explanation video" hint="Chosen by the editor" /></li>
                <li v-if="videoSearch">
                    <ExternalLinkItem
                        :href="videoSearch"
                        label="Search YouTube"
                        :hint="resources?.web.term ? '“' + resources.web.term + '”' : null"
                    />
                </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">No video for this question yet.</p>
        </ResourceCard>
    </div>
</template>
