<script setup>
const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
    current: {
        type: Boolean,
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

const removeHtml = (text) => {
    text = text.replace(/<[^>]*>/g, '')
    text = text.replace(/&nbsp;/g, '')
    text = text.replace(/&lt;/g, '<')
    text = text.replace(/&gt;/g, '>')
    return text
}

</script>
<template>
    <article class="p-4 border-1 border-b border-cgray max-h-[140px] hover:bg-main2 cursor-pointer" :class="{'bg-main1': current}">
        <h4 class="text-white text-[15px] font-bold">{{ (note.title) ? note.title : "Nueva nota" }}</h4>
        <p class="text-cgrey text-[13px] h-[60px] line-clamp line-clamp-3 mb-2">{{ (note.content) ? removeHtml(note.content) : "" }}</p>
        <span class="text-[13px] text-cgrey italic">{{ dateToString(note.created_at) }}</span>
    </article>
</template>
