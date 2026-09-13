<template>
    <div class="flex h-screen flex-col lg:flex-row">
        <div class="flex
                    w-full
                    h-1/2
                    md:w-1/2 md:h-full
            ">
            <div class="w-[300px] m-auto">
                <img alt="logo" src="/favicon.png" class="mb-8 w-[80px]">
                <h2 class="text-2xl mb-2 font-bold">Welcome to SimplyNotes</h2>
                <p class="text-sm mb-8 text-gray-500">You can login via Google account</p>
                <slot />
            </div>
        </div>
        <div class="overflow-hidden
            login-image
            w-full h-1/2
            md:w-1/2 md:h-full">
            <img src="../../assets/login-bg.webp" alt="login-bg" class="">
        </div>
    </div>
</template>
<style lang="scss">

.login-image{

    img {
        animation: zoom 60s infinite;
    }

}

@keyframes zoom{
    0%{
        transform: scale(1);
    }
    50%{
        transform: scale(1.2);
    }
    100%{
        transform: scale(1);
    }

}

</style>
<script setup lang="ts">
</script>
