<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
})

const classes = computed(() =>
    props.active
        // Fixed light-on-dark colours, like NavLink: dark: variants follow the OS theme, not the app's
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-(--main4) text-start text-base font-medium text-white bg-main1 focus:outline-hidden transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-400 hover:text-gray-200 hover:bg-main1 hover:border-gray-600 focus:outline-hidden focus:text-gray-200 transition duration-150 ease-in-out'
)
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
