<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SharedData, User } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const form = useForm({ leaderboard_opt_in: !!user.leaderboard_opt_in, leaderboard_name: user.leaderboard_name ?? '' });
function submit() {
    form.patch('/settings/leaderboard', { preserveScroll: true });
}
</script>
<template>
    <div class="flex flex-col space-y-6">
        <HeadingSmall title="Leaderboard" description="Off by default. Other students only ever see your display name, never your email." />
        <form class="space-y-5" @submit.prevent="submit">
            <label class="flex items-start gap-3 text-sm">
                <input v-model="form.leaderboard_opt_in" type="checkbox" class="mt-0.5 size-4 accent-primary" />
                <span
                    ><span class="font-medium">Show me on the leaderboard</span><br /><span class="text-muted-foreground"
                        >You can switch this off at any time.</span
                    ></span
                >
            </label>
            <div v-if="form.leaderboard_opt_in" class="grid gap-2">
                <Label for="leaderboard_name">Display name</Label>
                <Input id="leaderboard_name" v-model="form.leaderboard_name" minlength="3" maxlength="30" required placeholder="e.g. Dr Sara K" />
                <InputError :message="form.errors.leaderboard_name" />
            </div>
            <InputError :message="form.errors.leaderboard_opt_in" />
            <Button :disabled="form.processing">Save leaderboard settings</Button>
        </form>
    </div>
</template>
