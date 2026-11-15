<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    activeNavigationHref,
    type SidebarNavGroup,
} from '@/lib/sidebarNavigation';
import { toUrl } from '@/lib/utils';

const props = defineProps<{ groups: SidebarNavGroup[] }>();
const page = usePage();
const { state, isMobile, setOpen, setOpenMobile } = useSidebar();
const openGroup = ref<string | null>(null);
const activeHref = computed(() =>
    activeNavigationHref(
        props.groups.flatMap((group) => group.items),
        page.url,
    ),
);
const activeGroup = computed(
    () =>
        props.groups.find((group) =>
            group.items.some((item) => toUrl(item.href) === activeHref.value),
        )?.id,
);

watch(
    () => page.url,
    () => {
        openGroup.value = activeGroup.value ?? null;
    },
    { immediate: true },
);

function toggle(id: string) {
    if (state.value === 'collapsed' && !isMobile.value) {
        setOpen(true);
        openGroup.value = id;
        return;
    }
    openGroup.value = openGroup.value === id ? null : id;
}
</script>
<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="group in groups" :key="group.id">
                <SidebarMenuButton
                    type="button"
                    :tooltip="group.title"
                    :is-active="activeGroup === group.id"
                    :aria-expanded="
                        openGroup === group.id &&
                        (state !== 'collapsed' || isMobile)
                    "
                    :aria-controls="`sidebar-group-${group.id}`"
                    @click="toggle(group.id)"
                >
                    <component :is="group.icon" />
                    <span>{{ group.title }}</span>
                    <ChevronRight
                        class="ml-auto size-3.5 group-data-[collapsible=icon]:hidden"
                        :class="{ 'rotate-90': openGroup === group.id }"
                    />
                </SidebarMenuButton>
                <SidebarMenuSub
                    v-show="openGroup === group.id"
                    :id="`sidebar-group-${group.id}`"
                    class="dashboard-submenu"
                >
                    <SidebarMenuSubItem
                        v-for="item in group.items"
                        :key="toUrl(item.href)"
                    >
                        <SidebarMenuSubButton
                            as-child
                            :is-active="activeHref === toUrl(item.href)"
                        >
                            <Link
                                :href="item.href"
                                :aria-current="
                                    activeHref === toUrl(item.href)
                                        ? 'page'
                                        : undefined
                                "
                                @click="setOpenMobile(false)"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
