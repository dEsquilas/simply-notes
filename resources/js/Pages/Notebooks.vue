<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    notebooks: {
        type: Array,
        required: true,
    },
});

console.log(props.notebooks);

</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Notebooks</h2>
        </template>

        <div class="max-w-[1200px] w-auto m-auto py-12 grid lg:grid-cols-3 sm:grid-cols-2 gap-4 p-4">
            <article v-for="notebook in notebooks" :key="notebook.id" class="w-full mx-auto gap-4">
                <Link :href="'/notebook/' + notebook.id">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-semibold">{{ notebook.name }}</h3>
                        <p class="text-sm">{{ notebook.description }}</p>
                    </div>
                </div>
                </Link>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
