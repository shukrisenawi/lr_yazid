<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { paymentSettingsCopy as copy } from '@/lib/paymentSettingsCopy';
const props = defineProps<{
    settings: {
        enabled: boolean;
        environment: string;
        sandbox_category_code: string | null;
        production_category_code: string | null;
        version: number;
    };
    sandboxConfigured: boolean;
    productionConfigured: boolean;
    sandboxKeyAvailable: boolean;
    productionKeyAvailable: boolean;
    storedInDatabase: boolean;
    callbackReady: boolean;
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
    clear_sandbox_secret_key: false,
    clear_production_secret_key: false,
    version: props.settings.version,
});
function save() {
    form.put('/admin/payment-settings', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset(
                'sandbox_secret_key',
                'production_secret_key',
                'clear_sandbox_secret_key',
                'clear_production_secret_key',
            );
            form.version = props.settings.version;
        },
    });
}
</script>
<template>
    <Head :title="copy.title" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <Link href="/admin/website-settings" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">{{ copy.title }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <p class="mt-3 text-sm text-muted-foreground">
            {{
                storedInDatabase ? copy.sourceDatabase : copy.sourceEnvironment
            }}
        </p>
        <form
            class="mt-8 space-y-6 rounded-md border p-5 sm:p-6"
            @submit.prevent="save"
        >
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.environment }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><select
                    v-model="form.environment"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="sandbox">{{ copy.sandbox }}</option>
                    <option value="production">{{ copy.production }}</option>
                </select></label
            >
            <p class="text-xs leading-relaxed text-muted-foreground">
                {{ copy.categoryHelp }}
            </p>
            <div class="grid gap-8 md:grid-cols-2">
                <fieldset class="min-w-0 space-y-4">
                    <legend class="font-semibold">
                        {{ copy.sandboxTitle }}
                    </legend>
                    <p class="text-xs text-muted-foreground">
                        {{
                            sandboxConfigured
                                ? copy.configured
                                : copy.notConfigured
                        }}
                    </p>
                    <label class="grid gap-2 text-sm"
                        >{{ copy.sandboxCategory
                        }}<input
                            v-model="form.sandbox_category_code"
                            maxlength="64"
                            pattern="[A-Za-z0-9]+"
                            autocomplete="off"
                            class="min-w-0 rounded-md border bg-transparent p-2.5"
                    /></label>
                    <label class="grid gap-2 text-sm"
                        >{{ copy.sandboxKey
                        }}<input
                            v-model="form.sandbox_secret_key"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            maxlength="255"
                            class="min-w-0 rounded-md border bg-transparent p-2.5"
                        /><span
                            class="text-xs leading-relaxed text-muted-foreground"
                            >{{ copy.keyHelp }}</span
                        ></label
                    >
                    <label
                        v-if="sandboxKeyAvailable"
                        class="flex items-start gap-2 text-sm"
                        ><input
                            v-model="form.clear_sandbox_secret_key"
                            type="checkbox"
                            class="mt-1"
                        />{{ copy.removeSandbox }}</label
                    >
                </fieldset>
                <fieldset class="min-w-0 space-y-4">
                    <legend class="font-semibold">
                        {{ copy.productionTitle }}
                    </legend>
                    <p class="text-xs text-muted-foreground">
                        {{
                            productionConfigured
                                ? copy.configured
                                : copy.notConfigured
                        }}
                    </p>
                    <label class="grid gap-2 text-sm"
                        >{{ copy.productionCategory
                        }}<input
                            v-model="form.production_category_code"
                            maxlength="64"
                            pattern="[A-Za-z0-9]+"
                            autocomplete="off"
                            class="min-w-0 rounded-md border bg-transparent p-2.5"
                    /></label>
                    <label class="grid gap-2 text-sm"
                        >{{ copy.productionKey
                        }}<input
                            v-model="form.production_secret_key"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            maxlength="255"
                            class="min-w-0 rounded-md border bg-transparent p-2.5"
                        /><span
                            class="text-xs leading-relaxed text-muted-foreground"
                            >{{ copy.keyHelp }}</span
                        ></label
                    >
                    <label
                        v-if="productionKeyAvailable"
                        class="flex items-start gap-2 text-sm"
                        ><input
                            v-model="form.clear_production_secret_key"
                            type="checkbox"
                            class="mt-1"
                        />{{ copy.removeProduction }}</label
                    >
                </fieldset>
            </div>
            <label class="flex items-start gap-2 text-sm"
                ><input v-model="form.enabled" type="checkbox" class="mt-1" />{{
                    copy.enable
                }}</label
            >
            <p
                v-if="form.environment === 'production'"
                role="note"
                class="rounded-md border border-amber-600 p-4 text-sm leading-relaxed"
            >
                {{ copy.productionWarning }}
            </p>
            <p class="text-xs leading-relaxed text-muted-foreground">
                {{ copy.configuredWarning }}
            </p>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <button
                :disabled="form.processing || !!form.errors.version"
                class="rounded-md bg-emerald-800 px-5 py-3 text-sm text-white hover:bg-emerald-900 disabled:opacity-50"
            >
                {{ form.processing ? copy.saving : copy.save }}
            </button>
        </form>
        <section class="mt-8 rounded-md border p-5 text-sm sm:p-6">
            <h2 class="text-lg font-semibold">{{ copy.callbackTitle }}</h2>
            <p class="mt-3 leading-relaxed text-muted-foreground">
                {{ copy.callbackHelp }}
            </p>
            <p class="mt-4 rounded-md border p-4 leading-relaxed">
                {{ callbackReady ? copy.callbackReady : copy.callbackMissing }}
            </p>
            <dl class="mt-5 space-y-5">
                <div>
                    <dt class="text-muted-foreground">{{ copy.callback }}</dt>
                    <dd class="mt-2 font-mono text-xs break-all">
                        {{ callbackUrl }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ copy.return }}</dt>
                    <dd class="mt-2 font-mono text-xs break-all">
                        {{ returnUrl }}
                    </dd>
                </div>
            </dl>
            <p class="mt-5 leading-relaxed text-muted-foreground">
                {{ copy.securityHelp }}
            </p>
            <p class="mt-4 leading-relaxed text-muted-foreground">
                {{ copy.historyHelp }}
            </p>
        </section>
    </div>
</template>
