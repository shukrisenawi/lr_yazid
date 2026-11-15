<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
const page = usePage();
</script>
<template>
    <Head>
        <meta
            head-key="application-name"
            name="application-name"
            :content="page.props.website?.website_name || page.props.name"
        />
        <link
            head-key="website-icon"
            rel="icon"
            :href="page.props.website?.favicon_url || '/favicon.ico'"
            :type="
                page.props.website?.favicon_url ? 'image/webp' : 'image/x-icon'
            "
        />
    </Head>
</template>
