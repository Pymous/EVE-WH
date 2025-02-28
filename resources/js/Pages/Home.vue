<script setup>
import { Head, Link } from "@inertiajs/vue3";
import moduleMfgCalculator from "../Components/moduleMfgCalculator.vue";

defineProps({
    oresList: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="Logistics" />
    <div
        class="page-welcome max-w-5xl mx-auto flex flex-col items-center justify-center"
    >
        <div class="mt-12 flex gap-5 items-center flew-grow w-full">
            <div>
                <img src="../../img/logo.png" class="w-24" />
            </div>
            <div>
                <h1 class="leading-none">EVE-WH</h1>
                <h2 class="leading-none uppercase">
                    A Companion App for WH Logistics
                </h2>
            </div>
        </div>

        <module-mfg-calculator :oresList="oresList" />
    </div>
</template>
