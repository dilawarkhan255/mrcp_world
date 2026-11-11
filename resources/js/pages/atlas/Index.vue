<script setup lang="ts">
import AdminDelete from '@/components/AdminDelete.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AiQuickAsk from '@/components/practice/AiQuickAsk.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ExternalLink, ImageOff, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AtlasImage {
    id: number;
    title: string;
    diagnosis: string | null;
    subject: string | null;
    system: string | null;
    description: string | null;
    url: string;
    credit: string | null;
    source_url: string | null;
    license: string;
}

const props = defineProps<{
    exam: { id: number; name: string };
    filters: { q: string; subject: string };
    subjects: string[];
    images: { data: AtlasImage[]; current_page: number; prev_page_url: string | null; next_page_url: string | null };
}>();

const term = ref(props.filters.q);
const subject = ref(props.filters.subject);
const selected = ref<AtlasImage | null>(null);
const aiEnabled = computed(() => !!usePage<SharedData>().props.ai?.enabled);

function apply() {
    router.get(
        '/image-atlas',
        { q: term.value || undefined, subject: subject.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function pick(value: string) {
    subject.value = value;
    apply();
}
</script>
<template>
    <Head title="Image Atlas" />
    <AppLayout :breadcrumbs="[{ title: 'Image Atlas', href: '/image-atlas' }]">
        <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Image Atlas</h1>
                <p class="mt-2 text-sm text-muted-foreground">Clinical images with findings and teaching points. Search by title or diagnosis.</p>
            </div>

            <form class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" @submit.prevent="apply">
                <label class="grid min-w-[14rem] flex-1 gap-1.5 text-sm font-medium"
                    >Search
                    <span class="relative">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" aria-hidden="true" />
                        <input
                            v-model="term"
                            type="search"
                            maxlength="100"
                            placeholder="e.g. erythema nodosum"
                            class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </span>
                </label>
                <Button type="submit">Search</Button>
            </form>

            <div v-if="subjects.length" class="flex flex-wrap gap-2" role="group" aria-label="Filter by subject">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium"
                    :class="subject === '' ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:border-primary/50'"
                    :aria-pressed="subject === ''"
                    @click="pick('')"
                >
                    All subjects
                </button>
                <button
                    v-for="name in subjects"
                    :key="name"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium"
                    :class="subject === name ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:border-primary/50'"
                    :aria-pressed="subject === name"
                    @click="pick(name)"
                >
                    {{ name }}
                </button>
            </div>

            <div v-if="!images.data.length" class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                <ImageOff class="size-8 text-primary" aria-hidden="true" />
                <p class="text-sm text-muted-foreground">
                    {{ filters.q || filters.subject ? 'No images match these filters.' : 'No images have been published yet.' }}
                </p>
            </div>
            <ul v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <li v-for="image in images.data" :key="image.id">
                    <button
                        type="button"
                        class="group relative block w-full overflow-hidden rounded-xl border bg-card text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @click="selected = image"
                    >
                        <span class="mw-logo-line absolute inset-x-0 top-0 z-10 h-1" aria-hidden="true" />
                        <span class="block aspect-[4/3] overflow-hidden bg-muted">
                            <img
                                :src="image.url"
                                :alt="image.title"
                                loading="lazy"
                                class="h-full w-full object-cover transition group-hover:scale-[1.03]"
                            />
                        </span>
                        <span class="block space-y-1 p-3">
                            <span class="line-clamp-1 text-sm font-semibold">{{ image.title }}</span>
                            <span v-if="image.diagnosis" class="line-clamp-1 block text-xs text-muted-foreground">{{ image.diagnosis }}</span>
                            <span v-if="image.subject" class="mw-logo-soft inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold">{{
                                image.subject
                            }}</span>
                        </span>
                    </button>
                </li>
            </ul>

            <SimplePagination :previous="images.prev_page_url" :next="images.next_page_url" :page="images.current_page" />
        </div>

        <Dialog :open="selected !== null" @update:open="(open: boolean) => !open && (selected = null)">
            <DialogContent v-if="selected" class="max-h-[90vh] overflow-y-auto sm:max-w-3xl" :class="aiEnabled ? 'lg:max-w-5xl' : ''">
                <DialogHeader>
                    <DialogTitle>{{ selected.title }}</DialogTitle>
                    <DialogDescription>{{ [selected.diagnosis, selected.subject, selected.system].filter(Boolean).join(' · ') }}</DialogDescription>
                </DialogHeader>
                <div class="-mt-2 flex justify-end">
                    <AdminDelete
                        :url="'/staff/image-atlas/' + selected.id"
                        @deleted="selected = null"
                        label="Delete image"
                        confirm-text="Delete this image and its file for good? This cannot be undone."
                    />
                </div>
                <div class="grid gap-4" :class="aiEnabled ? 'lg:grid-cols-[minmax(0,1fr)_18rem]' : ''">
                    <div class="min-w-0 space-y-4">
                        <img :src="selected.url" :alt="selected.title" class="max-h-[60vh] w-full rounded-lg border bg-muted object-contain" />
                        <p v-if="selected.description" class="whitespace-pre-wrap text-sm leading-relaxed">{{ selected.description }}</p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="selected.credit">Image: {{ selected.credit }} · </span>{{ selected.license }}
                            <a
                                v-if="selected.source_url"
                                :href="selected.source_url"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="ml-1 inline-flex items-center gap-0.5 text-primary hover:underline"
                                >Source<ExternalLink class="size-3" aria-hidden="true"
                            /></a>
                        </p>
                    </div>
                    <AiQuickAsk
                        v-if="aiEnabled"
                        class="lg:self-start"
                        title="Ask AI about this image"
                        :url="'/image-atlas/' + selected.id + '/ask'"
                        :suggestions="['What are the key features?', 'What are the differentials?', 'What do examiners ask?']"
                        :reset-key="selected.id"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
