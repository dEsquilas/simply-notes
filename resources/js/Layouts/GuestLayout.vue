<template>
    <div class="flex h-screen">
        <div class="w-1/2 flex">
            <div class="w-[300px] m-auto">
                <img alt="logo" src="/favicon.png" class="mb-8 w-[80px]">
                <h2 class="text-2xl mb-2 font-bold">Welcome to SimplyNotes</h2>
                <p class="text-sm mb-8 text-gray-500">You can login via Google account</p>
                <slot />
            </div>
        </div>
        <div class="w-1/2 bg-cover bg-center login-image">

        </div>
    </div>
</template>
<style>

.login-image{

    background-image: url('../../assets/login-bg.webp');

}

</style>
