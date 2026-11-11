<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    exam: { id: number; name: string };
    types: { value: string; label: string }[];
    items: { id: number; type: string; message: string; status: string; created_at: string | null }[];
}>();

const form = useForm<{ type: string; message: string }>({ type: 'suggestion', message: '' });

function submit() {
    form.post('/feedback', {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
}
</script>
<template>
    <Head title="Feedback" />
    <AppLayout :breadcrumbs="[{ title: 'Feedback', href: '/feedback' }]">
        <div class="mx-auto w-full max-w-3xl space-y-8 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Feedback</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    Tell the MRCPWorld team about a suggestion, a problem with a question or explanation, or a bug. Include the question code when you
                    can.
                </p>
            </div>

            <form class="space-y-4 rounded-xl border bg-card p-6" @submit.prevent="submit">
                <label class="grid gap-2 text-sm font-medium"
                    >Type
                    <select required v-model="form.type" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                    <InputError :message="form.errors.type" />
                </label>
                <label class="grid gap-2 text-sm font-medium"
                    >Message
                    <textarea
                        required
                        v-model="form.message"
                        rows="6"
                        minlength="10"
                        maxlength="4000"
                        placeholder="At least 10 characters"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.message" />
                </label>
                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Sending...' : 'Send feedback' }}</Button>
                    <p v-if="form.wasSuccessful" class="text-sm text-primary">Thank you. Your feedback has been sent.</p>
                </div>
            </form>

            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Your recent feedback</h2>
                <p v-if="!items.length" class="text-sm text-muted-foreground">You have not sent any feedback yet.</p>
                <article v-for="item in items" :key="item.id" class="rounded-xl border bg-card p-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full bg-secondary px-2 py-0.5 font-semibold text-secondary-foreground">{{ item.type }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 font-semibold"
                            :class="item.status === 'new' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                            >{{ item.status === 'new' ? 'Received' : 'Reviewed' }}</span
                        >
                        <span v-if="item.created_at" class="text-muted-foreground">{{ new Date(item.created_at).toLocaleDateString() }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ item.message }}</p>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
