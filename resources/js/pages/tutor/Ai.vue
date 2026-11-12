<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/post-json';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Loader2, MessageSquarePlus, MessagesSquare, Pencil, Send, Sparkles, Trash2, X } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';

/**
 * The AI Tutor with saved conversations: the list on the left (open, rename,
 * delete), the conversation on the right. The server keeps the history and
 * sends the last turns to Claude, so a conversation continues on any device.
 */
interface Turn {
    role: 'user' | 'assistant';
    content: string;
    html: string | null;
}
interface ConversationItem {
    id: number;
    title: string;
    updated: string | null;
}
const props = defineProps<{
    exam: { id: number; name: string };
    enabled: boolean;
    remaining: number;
    conversations: ConversationItem[];
    conversation: { id: number; title: string; messages: Turn[] } | null;
}>();

const list = ref<ConversationItem[]>([...props.conversations]);
const currentId = ref<number | null>(props.conversation?.id ?? null);
const turns = ref<Turn[]>([...(props.conversation?.messages ?? [])]);
const message = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const remaining = ref(props.remaining);
const thread = ref<HTMLElement | null>(null);
const editing = ref<number | null>(null);
const editTitle = ref('');

watch(
    () => props.conversation?.id,
    () => {
        currentId.value = props.conversation?.id ?? null;
        turns.value = [...(props.conversation?.messages ?? [])];
        error.value = null;
        scroll(false);
    },
);
watch(
    () => props.conversations,
    (value) => (list.value = [...value]),
);
onMounted(() => scroll(false));

const STARTERS = [
    'Explain the causes of hyponatraemia and how to work them up',
    'What are the key features of sarcoidosis that MRCP likes to test?',
    'Compare type 1 and type 2 respiratory failure',
    'How do I interpret an ABG step by step?',
];

async function send(text?: string) {
    const value = (text ?? message.value).trim();
    if (value.length < 2 || loading.value) return;
    error.value = null;
    loading.value = true;
    turns.value.push({ role: 'user', content: value, html: null });
    message.value = '';
    await scroll();
    try {
        const { ok, status, data } = await postJson<{
            conversation?: ConversationItem;
            content?: string;
            html?: string;
            error?: string;
            remaining?: number;
        }>('/tutor/ai/send', { message: value, conversation_id: currentId.value });
        remaining.value = data.remaining ?? remaining.value;
        if (ok && data.conversation && data.content) {
            turns.value.push({ role: 'assistant', content: data.content, html: data.html ?? null });
            const item = data.conversation;
            list.value = [item, ...list.value.filter((existing) => existing.id !== item.id)];
            if (currentId.value !== item.id) {
                currentId.value = item.id;
                // The address follows the new conversation without reloading the page.
                window.history.replaceState(window.history.state, '', '/tutor/ai/' + item.id);
            }
        } else {
            turns.value.pop();
            message.value = value;
            error.value =
                data.error ?? (status === 429 ? 'Too many questions at once. Please wait a minute.' : 'The tutor is not available right now.');
        }
    } catch {
        turns.value.pop();
        message.value = value;
        error.value = 'The tutor could not be reached. Please try again.';
    } finally {
        loading.value = false;
        await scroll();
    }
}

function newConversation() {
    router.get('/tutor/ai');
}
function startRename(item: ConversationItem) {
    editing.value = item.id;
    editTitle.value = item.title;
}
function saveRename(item: ConversationItem) {
    const title = editTitle.value.trim();
    if (!title) return;
    router.patch('/tutor/ai/' + item.id, { title }, { preserveScroll: true, preserveState: true, onSuccess: () => (item.title = title) });
    editing.value = null;
}
function remove(item: ConversationItem) {
    if (!window.confirm('Delete this conversation? This cannot be undone.')) return;
    router.delete('/tutor/ai/' + item.id);
}

async function scroll(smooth = true) {
    await nextTick();
    thread.value?.lastElementChild?.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'end' });
}
</script>

<template>
    <Head title="AI Tutor" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Knowledge Tutor', href: '/tutor' },
            { title: 'AI Tutor', href: '/tutor/ai' },
        ]"
    >
        <div class="mx-auto w-full max-w-7xl p-4 md:p-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                    <h1 class="mt-1 flex items-center gap-2 text-3xl font-semibold">
                        <Sparkles class="size-7 text-primary" aria-hidden="true" />AI Tutor
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Ask about any topic of the syllabus and follow up as you would with a tutor. Your conversations are saved, so you can come
                        back to them on any device.
                    </p>
                </div>
                <Button v-if="enabled" variant="outline" @click="newConversation"
                    ><MessageSquarePlus class="size-4" aria-hidden="true" />New conversation</Button
                >
            </div>

            <div v-if="!enabled" class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                The AI Tutor is not switched on yet. Please check back soon.
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <!-- Saved conversations -->
                <aside
                    class="overflow-hidden rounded-2xl border bg-card shadow-sm lg:sticky lg:top-24 lg:max-h-[calc(100vh-8rem)] lg:self-start lg:overflow-y-auto"
                >
                    <header class="flex items-center gap-2 border-b px-4 py-3">
                        <MessagesSquare class="size-4 text-primary" aria-hidden="true" />
                        <h2 class="text-sm font-semibold uppercase tracking-wider">Your conversations</h2>
                    </header>
                    <p v-if="!list.length" class="px-4 py-6 text-sm text-muted-foreground">Your conversations are saved here.</p>
                    <ul v-else class="divide-y">
                        <li v-for="item in list" :key="item.id" :class="item.id === currentId ? 'bg-primary/5' : ''">
                            <form v-if="editing === item.id" class="flex items-center gap-1 px-3 py-2" @submit.prevent="saveRename(item)">
                                <label class="sr-only" :for="'rename-' + item.id">Conversation name</label>
                                <input
                                    :id="'rename-' + item.id"
                                    v-model="editTitle"
                                    maxlength="80"
                                    class="min-w-0 flex-1 rounded-md border bg-background px-2 py-1 text-sm"
                                    @keydown.esc="editing = null"
                                />
                                <button type="submit" class="rounded p-1 text-primary hover:bg-muted" aria-label="Save name">
                                    <Check class="size-4" />
                                </button>
                                <button type="button" class="rounded p-1 hover:bg-muted" aria-label="Cancel" @click="editing = null">
                                    <X class="size-4" />
                                </button>
                            </form>
                            <div v-else class="group flex items-center gap-1 pr-2">
                                <Link
                                    :href="'/tutor/ai/' + item.id"
                                    class="min-w-0 flex-1 px-4 py-3"
                                    :aria-current="item.id === currentId ? 'page' : undefined"
                                >
                                    <span class="block truncate text-sm font-medium" :class="item.id === currentId ? 'text-primary' : ''">{{
                                        item.title
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">{{ item.updated }}</span>
                                </Link>
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground opacity-60 hover:bg-muted hover:text-foreground group-hover:opacity-100"
                                    :aria-label="'Rename ' + item.title"
                                    @click="startRename(item)"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground opacity-60 hover:bg-muted hover:text-destructive group-hover:opacity-100"
                                    :aria-label="'Delete ' + item.title"
                                    @click="remove(item)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </li>
                    </ul>
                </aside>

                <!-- The conversation -->
                <section class="flex min-w-0 flex-col gap-4">
                    <div v-if="!turns.length" class="grid gap-3 sm:grid-cols-2" role="group" aria-label="Suggested topics">
                        <button
                            v-for="starter in STARTERS"
                            :key="starter"
                            type="button"
                            class="rounded-xl border bg-card p-4 text-left text-sm font-medium shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md"
                            :disabled="loading"
                            @click="send(starter)"
                        >
                            <Sparkles class="mb-2 size-4 text-primary" aria-hidden="true" />{{ starter }}
                        </button>
                    </div>

                    <ol ref="thread" class="space-y-4" aria-live="polite">
                        <li v-for="(turn, index) in turns" :key="index" :class="turn.role === 'user' ? 'flex justify-end' : ''">
                            <div
                                v-if="turn.role === 'user'"
                                class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-br-sm bg-primary px-4 py-3 text-sm text-primary-foreground"
                            >
                                {{ turn.content }}
                            </div>
                            <div v-else class="relative overflow-hidden rounded-2xl rounded-bl-sm border bg-card p-5 shadow-sm">
                                <span class="brand-rainbow absolute inset-x-0 top-0 h-0.5" aria-hidden="true" />
                                <RichText :html="turn.html ?? ''" class="text-sm" />
                            </div>
                        </li>
                        <li v-if="loading" class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 class="size-4 animate-spin" aria-hidden="true" />The tutor is writing…
                        </li>
                    </ol>

                    <form class="sticky bottom-4 flex gap-2 rounded-2xl border bg-card p-2 shadow-lg" @submit.prevent="send()">
                        <label for="tutor-message" class="sr-only">Your question for the tutor</label>
                        <textarea
                            id="tutor-message"
                            v-model="message"
                            rows="2"
                            maxlength="2000"
                            placeholder="Ask about a topic, a mechanism, a guideline…"
                            class="min-w-0 flex-1 resize-none rounded-xl bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
                            @keydown.enter.exact.prevent="send()"
                        />
                        <Button type="submit" class="self-end" :disabled="loading || message.trim().length < 2" aria-label="Send">
                            <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Send v-else class="size-4" aria-hidden="true" />
                        </Button>
                    </form>
                    <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                    <p class="text-[11px] text-muted-foreground">
                        It can be wrong: check important facts against the textbook and current NICE guidance. Do not enter patient details.
                        {{ remaining }} AI requests left today.
                    </p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
