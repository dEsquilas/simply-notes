<script setup>
defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-main1 focus:outline-none transition duration-150 ease-in-out text-white"
    >
        <slot />
    </Link>
</template>
