<script setup>
import ApplicationLogo from '@/Components/Laravel/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex h-screen">
        <div class="w-1/2 flex">
            <div class="w-[300px] m-auto">
                <img src="/favicon.png" class="mb-8" width="80">
                <h2 class="text-2xl mb-2 font-bold">Welcome to SimplyNotes</h2>
                <p class="text-sm mb-8 text-gray-500">You can login via Google account</p>
                <slot />
            </div>
        </div>
        <div class="w-1/2 login-image">

        </div>
    </div>
</template>
<style>

.login-image{

    background: url('../../assets/login-bg.webp') no-repeat center center;

}

</style>
