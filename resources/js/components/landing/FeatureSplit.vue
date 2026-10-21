<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        body: string;
        id?: string;
        tone?: 'white' | 'surface';
        visual?: 'left' | 'right';
    }>(),
    { tone: 'white', visual: 'right' },
);
</script>
<template>
    <section :id="id" class="scroll-mt-24 py-16 md:py-24" :class="tone === 'white' ? 'bg-white' : 'bg-surface'">
        <div class="mx-auto grid w-full max-w-[1280px] items-center gap-10 px-5 md:px-8 lg:grid-cols-2 lg:gap-16">
            <div :class="visual === 'left' ? 'lg:order-2' : 'lg:order-1'">
                <h2 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ title }}</h2>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-muted-strong">{{ body }}</p>
            </div>
            <div :class="visual === 'left' ? 'lg:order-1' : 'lg:order-2'">
                <slot />
            </div>
        </div>
    </section>
</template>
