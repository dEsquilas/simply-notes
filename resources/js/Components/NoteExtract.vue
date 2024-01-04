<script setup>
const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
})

const dateToString = (date) => {
    const d = new Date(date);
    const months = [
        'Ene',
        'Feb',
        'Mar',
        'Abr',
        'May',
        'Jun',
        'Jul',
        'Ago',
        'Sep',
        'Oct',
        'Nov',
        'Dic',
    ];

    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`
}

</script>
<template>
    <article class="p-4 border-1 border-b border-cgray min-h-[120px] hover:bg-main1 cursor-pointer">
        <h4 class="text-white text-[14px] font-bold">{{ (note.title) ? note.title : "Nueva nota" }}</h4>
        <p class="text-cgray text-[13px] min-h-[50px]">{{ (note.content) ? note.content : "" }}</p>
        <span class="text-[13px] text-cgray italic">{{ dateToString(note.created_at) }}</span>
    </article>
</template>
