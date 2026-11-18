<script setup lang="ts">
import ImageUpload from '@/components/ImageUpload.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { programCopy as copy } from '@/lib/programCopy';
import { localDateTime, type VipProgram } from '@/types/program';
const props = defineProps<{ program: VipProgram | null }>();
const uploadKey = ref(0);
const form = useForm({
    _method: props.program ? 'put' : 'post',
    title: props.program?.title ?? '',
    slug: props.program?.slug ?? '',
    short_description: props.program?.short_description ?? '',
    description: props.program?.description ?? '',
    starts_at: localDateTime(props.program?.starts_at),
    ends_at: localDateTime(props.program?.ends_at),
    registration_opens_at: localDateTime(props.program?.registration_opens_at),
    registration_closes_at: localDateTime(
        props.program?.registration_closes_at,
    ),
    location: props.program?.location ?? '',
    maps_url: props.program?.maps_url ?? '',
    capacity: props.program?.capacity ?? 30,
    status: props.program?.status ?? 'draft',
    registration_enabled: props.program?.registration_enabled ?? true,
    images: [] as File[],
    remove_image_ids: [] as number[],
});
function upload(event: Event) {
    form.images = Array.from((event.target as HTMLInputElement).files ?? []);
}
function save() {
    form._method = props.program ? 'put' : 'post';
    form.post(
        props.program
            ? `/admin/programs/${props.program.id}`
            : '/admin/programs',
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.images = [];
                form.remove_image_ids = [];
                uploadKey.value++;
            },
        },
    );
}
</script>
<template>
    <Head :title="program ? copy.edit : copy.create" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <Link href="/admin/programs" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">
            {{ program ? copy.edit : copy.create }}
        </h1>
        <Link
            v-if="program"
            :href="`/admin/programs/${program.id}/participants`"
            class="mt-4 inline-block text-sm underline"
            >{{ copy.participants }}</Link
        >
        <form class="mt-8 space-y-7" @submit.prevent="save">
            <div
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="rounded-md border border-red-300 p-4 text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') }}
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >{{ copy.name
                    }}<input
                        v-model="form.title"
                        required
                        maxlength="200"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.slug
                    }}<input
                        v-model="form.slug"
                        required
                        pattern="[a-z0-9]+(-[a-z0-9]+)*"
                        maxlength="200"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <label class="grid gap-2 text-sm"
                >{{ copy.short
                }}<textarea
                    v-model="form.short_description"
                    rows="3"
                    maxlength="1000"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <section>
                <h2 class="mb-3 text-sm font-medium">{{ copy.description }}</h2>
                <RichTextEditor
                    v-model="form.description"
                    :label="copy.description"
                />
            </section>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >{{ copy.starts
                    }}<input
                        v-model="form.starts_at"
                        type="datetime-local"
                        required
                        step="1"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.ends
                    }}<input
                        v-model="form.ends_at"
                        type="datetime-local"
                        required
                        step="1"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.opens
                    }}<input
                        v-model="form.registration_opens_at"
                        type="datetime-local"
                        required
                        step="1"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.closes
                    }}<input
                        v-model="form.registration_closes_at"
                        type="datetime-local"
                        required
                        step="1"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <p class="text-xs leading-relaxed text-muted-foreground">
                {{ copy.scheduleHelp }}
            </p>
            <label class="grid gap-2 text-sm"
                >{{ copy.location
                }}<input
                    v-model="form.location"
                    required
                    maxlength="500"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.maps
                }}<input
                    v-model="form.maps_url"
                    type="url"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >{{ copy.capacity
                    }}<input
                        v-model="form.capacity"
                        type="number"
                        min="1"
                        max="100000"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.status
                    }}<select
                        v-model="form.status"
                        class="rounded-md border bg-background p-2.5"
                    >
                        <option
                            v-for="(label, value) in copy.states"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select></label
                >
            </div>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="form.registration_enabled" type="checkbox" />{{
                    copy.enabled
                }}</label
            >
            <section class="rounded-xl border p-5">
                <h2 class="font-medium">{{ copy.images }}</h2>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ copy.imageHelp }}
                </p>
                <div
                    v-if="program?.images.length"
                    class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4"
                >
                    <label
                        v-for="image in program.images"
                        :key="image.id"
                        class="grid gap-2 text-sm"
                        ><img
                            :src="image.url"
                            :alt="program.title"
                            class="aspect-square w-full rounded-md object-cover"
                        /><span
                            ><input
                                v-model="form.remove_image_ids"
                                type="checkbox"
                                :value="image.id"
                            />
                            {{ copy.remove }}</span
                        ></label
                    >
                </div>
                <label class="mt-5 grid gap-2 text-sm"
                    >{{ copy.upload
                    }}<ImageUpload
                        :key="uploadKey"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        @change="upload"
                /></label>
            </section>
            <button
                :disabled="form.processing"
                class="rounded-md bg-emerald-800 px-6 py-3 text-white disabled:opacity-50"
            >
                {{ form.processing ? copy.saving : copy.save }}
            </button>
        </form>
    </div>
</template>
