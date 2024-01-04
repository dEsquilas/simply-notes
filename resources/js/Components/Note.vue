<script setup>
import editor from '@tinymce/tinymce-vue'
import { ref, onMounted } from 'vue'

const h = ref(window.innerHeight - 65)


</script>
<template>
    <div class="bg-purple-900 h-full">
        <editor class="h-full"
                api-key="r6bx0hha096m9aiy2o6swag7h983ouzxxe3vkruxdoxdpo3k"
                :init="{
                    height: h,
                    menubar: false,
                    skin: 'oxide-dark',
                    body_class: 'mceBlackBody',
                    plugins: [
                        'advlist',  'autolink',  'lists',  'link', 'image', 'charmap', 'preview',  'anchor',
                        'searchreplace', 'visualblocks', 'code', 'fullscreen', 'table',
                        'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount',
                    ],
                    toolbar: 'undo redo | formatselect | ' +
                        'bold italic backcolor | alignleft aligncenter ' +
                        'alignright alignjustify | bullist numlist outdent indent | table | ' +
                        'removeformat',
                    content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:18px; padding: 15px 25px; background: #212121; color: #ffffff; } p {margin: 0px;}',
                    lists_indent_on_tab: false,
                    indentation: '40px',
                    setup: function(editor) {
                                editor.on('keydown', function(e) {
                                if (e.keyCode === 9) { // código de tecla para Tab
                                    const node = editor.selection.getNode(); // Obtiene el nodo actual
                                    if (editor.dom.is(node, 'li,li *')) { // Verifica si el nodo es parte de una lista
                                        e.preventDefault(); // Previene el comportamiento predeterminado
                                        if (e.shiftKey) {
                                            editor.execCommand('Outdent'); // Si se presiona Shift + Tab, disminuye la indentación
                                        } else {
                                            console.log('detected')
                                            editor.execCommand('Indent'); // Si solo se presiona Tab, aumenta la indentación
                                        }
                                    } else {
                                        if (!e.shiftKey) {
                                            e.preventDefault(); // Previene el comportamiento predeterminado
                                            editor.execCommand('mceInsertContent', false, '&#x9;'); // Inserta un carácter de tabulación
                                        }
                                    }
                                }
                                });
                    }

                }"
        />
    </div>
</template>
