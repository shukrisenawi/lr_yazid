<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const profileVersion = ref(user.value.profile_version);
const avatarPreview = ref('');

function preview(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    const previous = avatarPreview.value;
    if (previous) URL.revokeObjectURL(previous);
    const url =
        file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
            ? URL.createObjectURL(file)
            : '';
    avatarPreview.value = url;
}
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            :action="ProfileController.update().url"
            method="post"
            class="space-y-6"
            v-slot="{ errors, processing }"
            @success="profileVersion = user.profile_version"
        >
            <input type="hidden" name="_method" value="patch" />
            <div class="grid gap-3">
                <Label for="avatar">Avatar</Label>
                <img
                    v-if="avatarPreview || user.avatar"
                    :src="avatarPreview || user.avatar"
                    alt="Avatar"
                    class="size-20 rounded-full object-cover"
                />
                <Input
                    id="avatar"
                    name="avatar"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="preview"
                />
                <p class="text-sm text-muted-foreground">
                    JPG, PNG atau WebP. Maksimum 2 MB, 2048 × 2048 piksel.
                </p>
                <InputError :message="errors.avatar" />
            </div>
            <input
                type="hidden"
                name="profile_version"
                :value="profileVersion"
            />
            <div v-if="errors.profile_version" role="alert" class="space-y-2">
                <InputError :message="errors.profile_version" />
                <a :href="edit().url" class="text-sm underline"
                    >Reload latest profile</a
                >
            </div>
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone number</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    autocomplete="tel"
                    :default-value="user.phone ?? ''"
                    required
                />
                <InputError :message="errors.phone" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>
</template>
