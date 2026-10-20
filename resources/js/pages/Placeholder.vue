<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Construction } from 'lucide-vue-next';
defineProps<{ title: string; href: string }>();
</script>
<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title, href }]">
        <div class="p-6 md:p-10">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Your learning space</p>
            <h1 class="mt-3 text-3xl font-semibold">{{ title }}</h1>
            <div class="mt-8 rounded-xl border border-dashed bg-card px-6 py-16 text-center">
                <Construction class="mx-auto size-9 text-primary" />
                <h2 class="mt-4 text-lg font-semibold">Coming soon</h2>
                <p class="mt-2 text-sm text-muted-foreground">This area will be available in a future phase.</p>
            </div>
            <div v-if="title === 'Previous sessions'" class="mt-6 rounded-xl border bg-card p-5">
                <DataTable
                    :columns="[
                        { accessorKey: 'date', header: 'Date' },
                        { accessorKey: 'exam', header: 'Exam' },
                        { accessorKey: 'status', header: 'Status' },
                    ]"
                    :data="[]"
                    ><template #empty>No study sessions yet.</template></DataTable
                >
            </div>
        </div>
    </AppLayout>
</template>
