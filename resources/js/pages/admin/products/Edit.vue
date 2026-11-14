<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import VariantAttributes from '@/components/VariantAttributes.vue';
import type { Product, Category, ProductVariant } from '@/types/catalog';
const props = defineProps<{
    product: Product | null;
    categories: Category[];
}>();
const localDate = (date: string | null | undefined) =>
    date
        ? new Intl.DateTimeFormat('sv-SE', {
              timeZone: 'Asia/Kuala_Lumpur',
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              hour12: false,
          })
              .format(new Date(date))
              .replace(' ', 'T')
        : '';
const form = useForm({
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    sku: props.product?.sku ?? '',
    category_id: props.product?.category_id ?? '',
    short_description: props.product?.short_description ?? '',
    description: props.product?.description ?? '',
    price_sen: props.product?.price_sen ?? 0,
    sale_price_sen: props.product?.sale_price_sen ?? null,
    sale_starts_at: localDate(props.product?.sale_starts_at),
    sale_ends_at: localDate(props.product?.sale_ends_at),
    stock: props.product?.stock ?? 0,
    weight_grams: props.product?.weight_grams ?? 0,
    status: props.product?.status ?? 'draft',
    is_featured: props.product?.is_featured ?? false,
    seo_title: props.product?.seo_title ?? '',
    meta_description: props.product?.meta_description ?? '',
    images: [] as File[],
    image_order: props.product?.images.map((image) => image.id) ?? [],
    variants: props.product?.variants ?? ([] as ProductVariant[]),
});
const dragged = ref<number | null>(null);
const previews = ref<{ name: string; url: string }[]>([]);
watch(
    () => form.images,
    (files, _, cleanup) => {
        previews.value = files.map((file) => ({
            name: file.name,
            url: URL.createObjectURL(file),
        }));
        const current = previews.value;
        cleanup(() =>
            current.forEach((image) => URL.revokeObjectURL(image.url)),
        );
    },
);
const destructive = useForm({});
const replacement = useForm({ image: null as File | null });
function replaceImage(id: number, event: Event) {
    replacement.image = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (replacement.image)
        replacement.post(`/admin/products/${props.product?.id}/images/${id}`, {
            preserveScroll: true,
            onSuccess: () => replacement.reset(),
        });
}
function moveImage(index: number, direction: number) {
    const target = index + direction;
    if (target < 0 || target >= form.image_order.length) return;
    const order = [...form.image_order];
    [order[index], order[target]] = [order[target], order[index]];
    form.image_order = order;
}
function save() {
    const onSuccess = () => {
        form.images = [];
        form.image_order = props.product?.images.map((image) => image.id) ?? [];
        form.variants = props.product?.variants ?? [];
        form.description = props.product?.description ?? form.description;
    };
    if (props.product)
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/admin/products/${props.product.id}`,
            { preserveScroll: true, onSuccess },
        );
    else form.post('/admin/products', { onSuccess });
}
function drop(id: number) {
    if (dragged.value === null) return;
    const order = [...form.image_order];
    order.splice(order.indexOf(dragged.value), 1);
    order.splice(order.indexOf(id), 0, dragged.value);
    form.image_order = order;
    dragged.value = null;
}
function primary(id: number) {
    form.image_order = [
        id,
        ...form.image_order.filter((value) => value !== id),
    ];
}
function remove(id: number) {
    if (confirm('Remove this product image?'))
        router.delete(`/admin/products/${props.product?.id}/images/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.image_order = form.image_order.filter(
                    (value) => value !== id,
                );
            },
        });
}
function archive() {
    if (confirm('Archive this product? It will no longer be publicly visible.'))
        destructive.delete(`/admin/products/${props.product?.id}`);
}
</script>
<template>
    <Head :title="product ? 'Edit product' : 'Add product'" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <Link href="/admin/products" class="text-sm text-muted-foreground"
            >← Products</Link
        >
        <h1 class="mt-5 text-3xl font-semibold">
            {{ product ? 'Edit product' : 'Add product' }}
        </h1>
        <form class="mt-8 space-y-8" @submit.prevent="save">
            <div
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="rounded-md border border-red-300 p-4 text-sm text-red-700 dark:text-red-400"
            >
                <p v-for="(message, key) in form.errors" :key="key">
                    {{ message }}
                </p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >Product name<input
                        v-model="form.name"
                        required
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Slug<input
                        v-model="form.slug"
                        required
                        pattern="[a-zA-Z0-9_\-]+"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >SKU<input
                        v-model="form.sku"
                        required
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Category<select
                        v-model="form.category_id"
                        class="rounded-md border bg-background p-2.5"
                    >
                        <option value="">Uncategorized</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select></label
                >
                <label class="grid gap-2 text-sm"
                    >Regular price (sen)<input
                        v-model="form.price_sen"
                        required
                        type="number"
                        min="0"
                        class="rounded-md border bg-transparent p-2.5"
                    /><span class="text-xs text-muted-foreground"
                        >100 sen = RM 1.00</span
                    ></label
                >
                <label class="grid gap-2 text-sm"
                    >Sale price (sen)<input
                        v-model="form.sale_price_sen"
                        type="number"
                        min="0"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Sale starts<input
                        v-model="form.sale_starts_at"
                        type="datetime-local"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Sale ends<input
                        v-model="form.sale_ends_at"
                        type="datetime-local"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Stock quantity<input
                        v-model="form.stock"
                        required
                        type="number"
                        min="0"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Weight (grams)<input
                        v-model="form.weight_grams"
                        required
                        type="number"
                        min="0"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Status<select
                        v-model="form.status"
                        class="rounded-md border bg-background p-2.5"
                    >
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select></label
                >
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_featured"
                        type="checkbox"
                    />Featured product</label
                >
            </div>
            <label class="grid gap-2 text-sm"
                >Short description<textarea
                    v-model="form.short_description"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <section>
                <h2 class="mb-3 font-medium">Product description</h2>
                <RichTextEditor v-model="form.description" />
            </section>
            <section>
                <h2 class="font-medium">Product images</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Drag to reorder, or use Set primary. The first image is the
                    featured image. Save to apply ordering.
                </p>
                <p
                    v-if="replacement.errors.image"
                    role="alert"
                    class="mt-3 text-sm text-red-700 dark:text-red-400"
                >
                    {{ replacement.errors.image }}
                </p>
                <div class="mt-4 flex flex-wrap gap-4">
                    <div
                        v-for="(id, index) in form.image_order"
                        :key="id"
                        draggable="true"
                        class="rounded-lg border p-2"
                        @dragstart="dragged = id"
                        @dragover.prevent
                        @drop.prevent="drop(id)"
                    >
                        <img
                            :src="
                                product?.images.find((image) => image.id === id)
                                    ?.url
                            "
                            alt="Product image"
                            class="size-28 rounded object-cover"
                        />
                        <div class="mt-2 grid gap-1">
                            <button
                                type="button"
                                class="text-xs underline disabled:opacity-40"
                                :disabled="index === 0"
                                @click="moveImage(index, -1)"
                            >
                                Move earlier
                            </button>
                            <button
                                type="button"
                                class="text-xs underline disabled:opacity-40"
                                :disabled="
                                    index === form.image_order.length - 1
                                "
                                @click="moveImage(index, 1)"
                            >
                                Move later
                            </button>
                            <label class="grid gap-1 text-xs"
                                >Replace image<input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="max-w-40 text-xs"
                                    :disabled="replacement.processing"
                                    @change="replaceImage(id, $event)"
                            /></label>
                            <button
                                type="button"
                                class="text-xs underline"
                                @click="primary(id)"
                            >
                                {{
                                    index === 0
                                        ? 'Primary image'
                                        : 'Set primary'
                                }}</button
                            ><button
                                type="button"
                                class="text-xs text-red-700 underline dark:text-red-400"
                                @click="remove(id)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
                <label class="mt-4 grid gap-2 text-sm"
                    >Upload images (JPEG, PNG or WebP, up to 5 MB each)<input
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        @change="
                            form.images = Array.from(
                                ($event.target as HTMLInputElement).files ?? [],
                            )
                        "
                /></label>
                <div class="mt-4 flex flex-wrap gap-3">
                    <img
                        v-for="image in previews"
                        :key="image.url"
                        :src="image.url"
                        :alt="image.name"
                        class="size-24 rounded-md object-cover"
                    />
                </div>
                <p v-if="form.images.length" class="mt-2 text-sm">
                    {{ form.images.length }} new images selected
                </p>
            </section>
            <section>
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-medium">Optional variants</h2>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-2 text-sm"
                        @click="
                            form.variants.push({
                                name: '',
                                sku: '',
                                price_sen: form.price_sen,
                                stock: 0,
                                is_active: true,
                                product_image_id: null,
                                attributes: [],
                            })
                        "
                    >
                        Add variant
                    </button>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    Use names such as “Black / Large”. Deactivate variants to
                    preserve their history.
                </p>
                <div
                    v-for="(variant, index) in form.variants"
                    :key="index"
                    class="mt-4 grid gap-3 rounded-md border p-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <label class="grid gap-2 text-xs"
                        >Variant image<select
                            v-model="variant.product_image_id"
                            class="min-w-0 rounded border bg-background p-2"
                        >
                            <option :value="null">Product default</option>
                            <option
                                v-for="(
                                    image, imagePosition
                                ) in product?.images ?? []"
                                :key="image.id"
                                :value="image.id"
                            >
                                Image {{ imagePosition + 1 }}
                            </option>
                        </select></label
                    >
                    <div class="sm:col-span-2 lg:col-span-5">
                        <VariantAttributes v-model="variant.attributes" />
                    </div>
                    <label class="grid gap-2 text-xs"
                        >Option name<input
                            v-model="variant.name"
                            required
                            class="min-w-0 rounded border bg-transparent p-2" /></label
                    ><label class="grid gap-2 text-xs"
                        >Variant SKU<input
                            v-model="variant.sku"
                            required
                            class="min-w-0 rounded border bg-transparent p-2" /></label
                    ><label class="grid gap-2 text-xs"
                        >Price (sen)<input
                            v-model="variant.price_sen"
                            type="number"
                            min="0"
                            required
                            class="min-w-0 rounded border bg-transparent p-2" /></label
                    ><label class="grid gap-2 text-xs"
                        >Stock<input
                            v-model="variant.stock"
                            type="number"
                            min="0"
                            required
                            class="min-w-0 rounded border bg-transparent p-2" /></label
                    ><label class="flex items-center gap-2 text-xs"
                        ><input
                            v-model="variant.is_active"
                            type="checkbox"
                        />Available</label
                    >
                </div>
            </section>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >SEO title<input
                        v-model="form.seo_title"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >Meta description<textarea
                        v-model="form.meta_description"
                        class="rounded-md border bg-transparent p-2.5"
                    />
                </label>
            </div>
            <button
                class="rounded-md bg-emerald-800 px-6 py-3 text-white disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save product' }}
            </button>
        </form>
        <div
            v-if="product"
            class="mt-8 flex flex-wrap gap-5 border-t pt-6 text-sm"
        >
            <Link
                v-if="product.status === 'active'"
                :href="`/products/${product.slug}`"
                class="underline"
                >View product</Link
            ><button
                :disabled="destructive.processing"
                class="underline"
                @click="
                    destructive.post(`/admin/products/${product.id}/duplicate`)
                "
            >
                Duplicate product</button
            ><button
                :disabled="destructive.processing"
                class="text-red-700 underline dark:text-red-400"
                @click="archive"
            >
                Archive product
            </button>
        </div>
    </div>
</template>
