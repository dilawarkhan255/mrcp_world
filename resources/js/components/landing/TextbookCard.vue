<script setup lang="ts">
const topics = [
    { title: 'Classification and presentation', detail: 'STEMI, NSTEMI and unstable angina' },
    { title: 'Immediate management', detail: 'Reperfusion, antiplatelet therapy, risk stratification' },
    { title: 'Complications', detail: 'Arrhythmia, cardiogenic shock, pericarditis' },
    { title: 'Secondary prevention', detail: 'Statins, smoking cessation, cardiac rehabilitation' },
];
</script>
<template>
    <div class="rounded-card border bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-widest text-teal">Sample topic outline</p>
        <h3 class="mt-2 font-heading text-xl font-semibold text-ink">Acute coronary syndrome</h3>
        <ul class="mt-5 space-y-3">
            <li
                v-for="(topic, index) in topics"
                :key="topic.title"
                class="border-l-2 pl-4"
                :class="index === 2 ? 'ml-6' : ''"
                style="border-color: var(--teal)"
            >
                <p class="text-sm font-semibold text-ink">{{ topic.title }}</p>
                <p class="mt-1 text-sm text-muted-soft">{{ topic.detail }}</p>
            </li>
        </ul>
    </div>
</template>
