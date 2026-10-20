<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PageSlice } from '@/types/practice';
import { Head, useForm } from '@inertiajs/vue3';
defineProps<{ exam: { id: number; name: string }; papers: PageSlice<{ id: number; title: string; year: number; questions_count: number }> }>();
const form = useForm<{ count: number; past_paper_id: number | null }>({ count: 50, past_paper_id: null });
function start(id: number) {
    form.past_paper_id = id;
    form.post('/questions/sessions');
}
</script>
<template>
    <Head title="Past papers" />
    <AppLayout :breadcrumbs="[{ title: 'Past papers', href: '/questions/past-papers' }]">
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Past papers</h1>
                <p class="mt-3 text-sm text-muted-foreground">Practise published questions organised by paper.</p>
            </div>
            <label class="flex items-center gap-3 text-sm"
                >Questions per session<input
                    v-model.number="form.count"
                    type="number"
                    min="1"
                    max="100"
                    class="w-24 rounded-md border bg-card px-3 py-2" /></label
            ><InputError :message="form.errors.count || form.errors.past_paper_id" />
            <div v-if="!papers.data.length" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                No published past papers yet.
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <section v-for="paper in papers.data" :key="paper.id" class="rounded-xl border bg-card p-6">
                    <p class="text-sm font-semibold text-primary">{{ paper.year }}</p>
                    <h2 class="mt-2 text-lg font-semibold">{{ paper.title }}</h2>
                    <p class="my-4 text-sm text-muted-foreground">{{ paper.questions_count }} published questions</p>
                    <Button :disabled="form.processing || !paper.questions_count" @click="start(paper.id)">Start paper practice</Button>
                </section>
            </div>
            <SimplePagination :previous="papers.prev_page_url" :next="papers.next_page_url" :page="papers.current_page" />
        </div>
    </AppLayout>
</template>
