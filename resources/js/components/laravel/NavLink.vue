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

// The app is always dark: fixed light colours, since dark: variants follow the OS theme and left the active link dark on dark
const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-(--main4) text-sm font-medium leading-5 text-white focus:outline-hidden transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-400 hover:text-gray-200 hover:border-gray-600 focus:outline-hidden focus:text-gray-200 transition duration-150 ease-in-out'
)
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
