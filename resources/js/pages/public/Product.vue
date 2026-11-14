<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProductCard from '@/components/ProductCard.vue';
import AddToCartForm from '@/components/AddToCartForm.vue';
import { money, type Product } from '@/types/catalog';
const props = defineProps<{ product: Product; related: Product[] }>();
const imageIndex = ref(0);
const variantId = ref<number | ''>('');
const variant = computed(() =>
    props.product.variants?.find((item) => item.id === variantId.value),
);
watch(variant, (value) => {
    const index = props.product.images.findIndex(
        (image) => image.id === value?.product_image_id,
    );
    if (index >= 0) imageIndex.value = index;
});
</script>
<template>
    <Head :title="product.seo_title || product.name"
        ><meta
            name="description"
            :content="product.meta_description || product.short_description"
    /></Head>
    <div class="mx-auto max-w-7xl px-6 py-10">
        <Link
            href="/products"
            class="text-sm text-muted-foreground hover:underline"
            >← All products</Link
        >
        <div class="mt-8 grid gap-10 md:grid-cols-2">
            <section aria-label="Product gallery">
                <img
                    v-if="product.images[imageIndex]"
                    :src="product.images[imageIndex].url"
                    :alt="product.images[imageIndex].alt || product.name"
                    class="aspect-square w-full rounded-lg object-cover"
                    width="800"
                    height="800"
                />
                <div
                    v-else
                    class="flex aspect-square items-center justify-center rounded-lg bg-muted text-muted-foreground"
                >
                    No image available
                </div>
                <div class="mt-4 flex gap-3 overflow-x-auto">
                    <button
                        v-for="(image, index) in product.images"
                        :key="image.id"
                        :aria-label="`View image ${index + 1}`"
                        :aria-pressed="imageIndex === index"
                        class="shrink-0 rounded-md border p-1"
                        @click="imageIndex = index"
                    >
                        <img
                            :src="image.url"
                            :alt="image.alt"
                            class="size-20 rounded object-cover"
                        />
                    </button>
                </div>
            </section>
            <section>
                <p class="text-sm text-muted-foreground">
                    {{ product.category?.name }}
                </p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight">
                    {{ product.name }}
                </h1>
                <p class="mt-6 text-2xl">
                    {{ money(variant?.price_sen ?? product.current_price_sen) }}
                    <del
                        v-if="
                            !variant &&
                            product.current_price_sen < product.price_sen
                        "
                        class="ml-2 text-lg text-muted-foreground"
                        >{{ money(product.price_sen) }}</del
                    >
                </p>
                <p class="mt-6 leading-relaxed text-muted-foreground">
                    {{ product.short_description }}
                </p>
                <label
                    v-if="product.variants?.length"
                    class="mt-6 grid max-w-sm gap-2 text-sm"
                    >Choose an option<select
                        v-model="variantId"
                        class="rounded-md border bg-background p-3"
                    >
                        <option value="">Select an option</option>
                        <option
                            v-for="item in product.variants"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }} · {{ money(item.price_sen) }}
                        </option>
                    </select></label
                >
                <p class="mt-6 text-sm">
                    {{
                        product.type === 'membership'
                            ? 'Membership · No shipping'
                            : (variant?.stock ?? product.stock) > 0
                              ? 'In stock'
                              : 'Out of stock'
                    }}
                </p>
                <dl
                    v-if="variant?.attributes?.length"
                    class="mt-4 grid grid-cols-2 gap-2 text-sm"
                >
                    <template
                        v-for="attribute in variant.attributes"
                        :key="attribute.name"
                        ><dt class="text-muted-foreground">
                            {{ attribute.name }}
                        </dt>
                        <dd>{{ attribute.value }}</dd></template
                    >
                </dl>
                <p class="mt-3 text-xs text-muted-foreground">
                    SKU: {{ variant?.sku ?? product.sku }}
                </p>
                <AddToCartForm
                    :product-id="product.id"
                    :variant-id="variant?.id ?? null"
                    :available="
                        product.type === 'membership'
                            ? 1
                            : (variant?.stock ?? product.stock)
                    "
                    :requires-variant="
                        (product.variants?.length ?? 0) > 0 ||
                        (product.variants_count ?? 0) > 0
                    "
                />
            </section>
        </div>
        <section class="mt-16 max-w-3xl">
            <h2 class="mb-6 text-2xl font-semibold">About this product</h2>
            <div class="rich-content" v-html="product.description" />
        </section>
        <section v-if="related.length" class="mt-16">
            <h2 class="mb-8 text-2xl font-semibold">You may also like</h2>
            <div class="grid gap-6 sm:grid-cols-3">
                <ProductCard
                    v-for="item in related"
                    :key="item.id"
                    :product="item"
                />
            </div>
        </section>
    </div>
</template>
