<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import PhoneNumberForm from '@/components/make-call/PhoneNumberForm.vue';
import VerifyPhoneForm from '@/components/make-call/VerifyPhoneForm.vue';
import MakeCallForm from '@/components/make-call/MakeCallForm.vue';
import CallHistory from '@/components/make-call/CallHistory.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <PhoneNumberForm/>
                <VerifyPhoneForm/>
                <MakeCallForm/>
            </div>
            <div class="relative min-h-[100vh] p-4 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <CallHistory />
            </div>
        </div>
    </AppLayout>
</template>
