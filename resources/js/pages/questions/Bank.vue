<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { PageSlice } from '@/types/practice';
import { Head, useForm, usePage } from '@inertiajs/vue3';
defineProps<{
    exam: { id: number; name: string };
    availableQuestions: number;
    categories: PageSlice<{ id: number; name: string; questions_count: number }>;
}>();
const page = usePage<SharedData>();
const form = useForm<{ count: number; category_id: number | null }>({ count: 10, category_id: null });
function start(category: number | null) {
    form.category_id = category;
    form.post('/questions/sessions');
}
</script>
<template>
    <Head title="Question bank" />
    <AppLayout :breadcrumbs="[{ title: 'Question bank', href: '/questions/bank' }]">
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Question bank</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ availableQuestions.toLocaleString() }} published questions. Choose a category or practise across the bank.
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-4 rounded-xl border bg-card p-5">
                <label class="grid gap-2 text-sm font-medium"
                    >Questions per session<input
                        v-model.number="form.count"
                        type="number"
                        min="1"
                        max="100"
                        class="w-36 rounded-md border bg-background px-3 py-2"
                /></label>
                <Button :disabled="form.processing || !availableQuestions" @click="start(null)">Start mixed practice</Button>
                <p class="w-full text-xs text-muted-foreground">
                    Up to 100 questions per session. If fewer are available, all matching questions are included.
                </p>
                <InputError :message="form.errors.count || form.errors.category_id" />
            </div>
            <div v-if="!availableQuestions" class="rounded-xl border border-dashed p-8 text-center">
                <h2 class="font-semibold">No published questions yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">Questions will appear after an editor adds and publishes them.</p>
                <a v-if="page.props.auth.canManageContent" href="/admin/questions" class="mt-4 inline-block text-sm font-semibold text-primary"
                    >Manage questions →</a
                >
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <section v-for="category in categories.data" :key="category.id" class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">{{ category.name }}</h2>
                    <p class="my-3 text-sm text-muted-foreground">{{ category.questions_count }} questions</p>
                    <Button variant="outline" :disabled="form.processing || !category.questions_count" @click="start(category.id)"
                        >Practise category</Button
                    >
                </section>
            </div>
            <SimplePagination :previous="categories.prev_page_url" :next="categories.next_page_url" :page="categories.current_page" />
        </div>
    </AppLayout>
</template>
