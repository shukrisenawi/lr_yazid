<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
const props = defineProps<{
    settings: {
        enabled: boolean;
        environment: string;
        sandbox_category_code: string | null;
        production_category_code: string | null;
    };
    sandboxConfigured: boolean;
    productionConfigured: boolean;
    callbackUrl: string;
    returnUrl: string;
}>();
const form = useForm({
    enabled: props.settings.enabled,
    environment: props.settings.environment,
    sandbox_category_code: props.settings.sandbox_category_code ?? '',
    production_category_code: props.settings.production_category_code ?? '',
    sandbox_secret_key: '',
    production_secret_key: '',
});
</script>
<template>
    <Head title="Payment Settings" />
    <div class="max-w-4xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">ToyyibPay settings</h1>
        <p class="mt-3 text-muted-foreground">
            Start with sandbox credentials. No secret key is ever sent back to
            this page.
        </p>
        <form
            class="mt-8 space-y-6 rounded-xl border p-6"
            @submit.prevent="
                form.put('/admin/payment-settings', {
                    onSuccess: () =>
                        form.reset(
                            'sandbox_secret_key',
                            'production_secret_key',
                        ),
                })
            "
        >
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <label class="grid gap-2 text-sm"
                >Payment environment<select
                    v-model="form.environment"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="sandbox">Sandbox (test payments)</option>
                    <option value="production">
                        Production (real payments)
                    </option>
                </select></label
            >
            <div class="grid gap-6 md:grid-cols-2">
                <fieldset class="space-y-4">
                    <legend class="font-semibold">Sandbox</legend>
                    <p class="text-xs text-muted-foreground">
                        {{
                            sandboxConfigured
                                ? 'Credentials configured'
                                : 'Not configured'
                        }}
                    </p>
                    <label class="grid gap-2 text-sm"
                        >Sandbox category code<input
                            v-model="form.sandbox_category_code"
                            class="rounded-md border bg-transparent p-2.5"
                            autocomplete="off" /></label
                    ><label class="grid gap-2 text-sm"
                        >Sandbox secret key<input
                            v-model="form.sandbox_secret_key"
                            type="password"
                            autocomplete="new-password"
                            class="rounded-md border bg-transparent p-2.5"
                        /><span class="text-xs text-muted-foreground"
                            >Leave blank to keep the existing key.</span
                        ></label
                    >
                </fieldset>
                <fieldset class="space-y-4">
                    <legend class="font-semibold">Production</legend>
                    <p class="text-xs text-muted-foreground">
                        {{
                            productionConfigured
                                ? 'Credentials configured'
                                : 'Not configured'
                        }}
                    </p>
                    <label class="grid gap-2 text-sm"
                        >Production category code<input
                            v-model="form.production_category_code"
                            class="rounded-md border bg-transparent p-2.5"
                            autocomplete="off" /></label
                    ><label class="grid gap-2 text-sm"
                        >Production secret key<input
                            v-model="form.production_secret_key"
                            type="password"
                            autocomplete="new-password"
                            class="rounded-md border bg-transparent p-2.5"
                        /><span class="text-xs text-muted-foreground"
                            >Leave blank to keep the existing key.</span
                        ></label
                    >
                </fieldset>
            </div>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="form.enabled" type="checkbox" />Enable payment
                collection in the selected environment</label
            >
            <p
                v-if="form.environment === 'production'"
                role="alert"
                class="rounded-md border p-4 text-sm"
            >
                Production mode takes real payments. Complete sandbox acceptance
                testing before enabling it.
            </p>
            <button
                :disabled="form.processing"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
            >
                {{ form.processing ? 'Saving…' : 'Save payment settings' }}
            </button>
        </form>
        <section class="mt-8 rounded-xl border p-6 text-sm">
            <h2 class="font-semibold">Callback configuration</h2>
            <p class="mt-3 text-muted-foreground">
                APP_URL must be a publicly reachable HTTPS URL. Localhost cannot
                receive provider callbacks.
            </p>
            <dl class="mt-4 space-y-4">
                <div>
                    <dt class="text-muted-foreground">
                        Server callback (POST)
                    </dt>
                    <dd class="mt-1 font-mono text-xs break-all">
                        {{ callbackUrl }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Browser return (GET)</dt>
                    <dd class="mt-1 font-mono text-xs break-all">
                        {{ returnUrl }}
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-muted-foreground">
                The browser return never marks an order paid. The server
                verifies the callback hash and reconciles the bill through
                ToyyibPay.
            </p>
        </section>
    </div>
</template>
