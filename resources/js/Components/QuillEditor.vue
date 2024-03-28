<template>
    <div ref="editor">
        Editor
    </div>
</template>
<script>
import Quill from 'quill'
import { onMounted }  from 'vue'

let quill = new Quill('#editor', {
    debug: 'info',
    modules: {
        toolbar: true,
    },
    placeholder: 'Compose an epic...',
    theme: 'snow'
})

onMounted(() => {

})

</script>
