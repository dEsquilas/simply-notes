<template>
    <Head title="Import Evernote Content" />
    <AuthenticatedLayout>
        <section class="flex flex-row max-w-[1200px] mx-auto w-full p-8 gap-8 overflow-y-auto">
            <section class="flex flex-col w-1/2 gap-4">
                <h2 class="text-2xl font-bold mb-8">Import Evernote Content</h2>
                <form class="flex flex-col gap-4 w-full pr-8" @submit.prevent="submit">
                    <fieldset>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="file_input">Upload the zip file:</label>
                        <input
                            type="file"
                            @change="handleFile"
                            class="block w-full border rounded-lg cursor-pointer focus:outline-0 focus:ring-0 bg-gray-700 border-gray-600  placeholder-gray-400 text-sm text-white file:py-3 file:px-3 file:mr-4 file:bg-main3 file:text-white file:font-bold file:text-sm file:border-0 hover:file:cursor-pointer"
                        />
                    </fieldset>
                    <fieldset>
                        <label class="block mt-4 mb-2 text-sm font-medium text-gray-900 dark:text-white" for="notebook">Select the notebook:</label>
                        <select v-model="notebook" id="countries" class="border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-0 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option value=""></option>
                            <option value="-1">New Notebook</option>
                            <option v-for="notebook in notebooks" :value="notebook.id">{{ notebook.name }} </option> // Add this line
                        </select>
                        <input v-model="newNotebookName" type="text" v-if="notebook == -1" placeholder="Enter the name of the new notebook" class="mt-4 border text-sm rounded-lg block w-full p-2.5 bg-gray-700 border-gray-600 placeholder-gray-400 text-white focus:ring-0" />
                    </fieldset>
                    <fieldset>
                        <button v-show="!isUploading" class="mt-4 bg-main3 text-white font-bold py-2 px-4 rounded-lg">Import</button>
                        <ArrowPathIcon v-show="isUploading" class="w-6 h-6 text-main3 animate-spin" />
                    </fieldset>
                </form>
            </section>
            <section class="flex flex-col gap-8 w-full overflow-y-auto">
                <section class="w-full">
                    <h2 class="text-2xl font-bold mb-8">Current jobs</h2>
                    <p v-show="runningJobs.length === 0">There are no jobs running.</p>
                    <article v-show="runningJobs.length > 0" class="w-full">
                        <header class="flex flex-row gap-4 w-full border-b border-cgray pb-4 mb-4 px-4">
                            <div class="w-1/3">
                                Destination
                            </div>
                            <div class="w-1/3 text-center">
                                Notes
                            </div>
                            <div class="w-1/3 text-right">
                                Status
                            </div>
                        </header>
                        <ul class="grid grid-cols-1 gap-4 px-4 w-full">
                            <li v-for="job in runningJobs" :key="job.id" class="flex flex-row justify-between items-center w-full hover:opacity-50">
                                <Link :href="'/notebook/' + job.notebook.id" class="w-1/3 text-main3">
                                    {{ job.notebook.name }}
                                </Link>
                                <div class="w-1/3 text-center">
                                    {{ remainingFiles(job) }}
                                </div>
                                <div class="w-1/3 text-right">
                                    <span v-show="job.status === 'pending'" class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Pending</span>
                                    <span v-show="job.status === 'running'" class="text-xs font-medium px-2.5 py-0.5 rounded bg-blue-900 text-blue-300">Running</span>
                                </div>
                            </li>
                        </ul>
                    </article>
                </section>
                <section class="w-full">
                    <h2 class="text-2xl font-bold mb-8">Finished jobs</h2>
                    <p v-show="finishedJobs.length === 0">There are no jobs completed.</p>
                    <article v-show="finishedJobs.length > 0" class="w-full">
                        <header class="flex flex-row gap-4 w-full border-b border-cgray pb-4 mb-4 px-4">
                            <div class="w-1/3">
                                Destination
                            </div>
                            <div class="w-1/3 text-center">
                                Notes
                            </div>
                            <div class="w-1/3 text-right">
                                End
                            </div>
                        </header>
                        <ul class="grid grid-cols-1 gap-4 px-4 w-full">
                            <li v-for="job in finishedJobs" :key="job.id" class="flex flex-row justify-between items-center w-full hover:opacity-50">
                                <Link :href="'/notebook/' + job.notebook.id" class="w-1/3 text-main3">
                                    {{ job.notebook.name }}
                                </Link>
                                <div class="w-1/3 text-center">
                                    {{ job.processed_files }}
                                </div>
                                <div class="w-1/3 text-right text-sm">
                                    {{ new Date(job.updated_at).toISOString().split('T')[0].replace(/-/g, '/') }}
                                </div>
                            </li>
                        </ul>
                    </article>
                </section>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
<script setup>
import axios from 'axios'
import { ArrowPathIcon } from "@heroicons/vue/24/outline"
import { notify } from "@kyvg/vue3-notification"
import { ref } from 'vue'

const props = defineProps({
    notebooks: Array,
    runningJobs: Array,
    finishedJobs: Array
})

const isUploading = ref(false)
const notebook = ref("")
const newNotebookName = ref("")
const runningJobs = ref(props.runningJobs)
const finishedJobs = ref(props.finishedJobs)
const file = ref("")

let isPolling = false


const handleFile = (e) => {
    file.value = e.target.files[0]
}

const submit = () => {

    if(file.value === "" || notebook.value === "") {
        notify({
            type: 'error',
            text: 'You should select a file and a notebook.',
        })
        return
    }

    isUploading.value = true

    const formData = new FormData()
    formData.append('file', file.value)
    formData.append('notebook', notebook.value)
    formData.append('notebookName', newNotebookName.value)

    axios.post('/import', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }).then((response) => {
        runningJobs.value.push(response.data.job)
        notebook.value = ""
        file.value = ""
        newNotebookName.value = ""
        if(!isPolling) {
            isPolling = true
            polling()
        }
    }).catch((error) => {
        console.log(error)
    }).finally(() => {
        isUploading.value = false
    })

}

const remainingFiles = (job) => {
    if(job.processed_files == null && job.total_files == null)
        return "Calculating..."
    else
        return job.processed_files + " / " + job.total_files
}

const polling = () => {

    axios.get('/import/polling').then((response) => {
        runningJobs.value = response.data.runningJobs
        finishedJobs.value = response.data.finishedJobs
        if(runningJobs.value.length === 0)
            isPolling = false
    }).catch((error) => {
        console.log(error)
    }).finally(() => {
        if(isPolling)
            setTimeout(polling, 1000)
    })

}

if(runningJobs.value.length > 0) {
    isPolling = true
    polling()
}

</script>
