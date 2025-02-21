<script setup>
import solver from "javascript-lp-solver";
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";

const props = defineProps({
    ores: {
        type: Object,
        required: true,
    },
});

// Define the model

const model = {
    optimize: "cost",
    opType: "min",
    constraints: {
        // For a Drake 10ME
        Tritanium: { min: 486000 },
        Pyerite: { min: 162000 },
        Mexallon: { min: 32400 },
        Isogen: { min: 9000 },
        Nocxium: { min: 1350 },
        Zydrine: { min: 315 },
        Megacyte: { min: 126 },
    },
    variables: props.ores,
    ints: {
        Tritanium: 1,
        Pyerite: 1,
        Mexallon: 1,
        Isogen: 1,
        Nocxium: 1,
        Zydrine: 1,
        Megacyte: 1,
        cost: 1,
    },
};

// Solve the problem
const results = ref(null);
const listOres = ref([]);

onMounted(() => {
    results.value = solver.Solve(model);

    for (const [ore, quantity] of Object.entries(results.value)) {
        // Check if "ore" exist in the props.ores object, if yes, noyce
        if (props.ores[ore]) {
            // Add the ore and quantity (rounded to the next 100) to the listOres array
            listOres.value.push({
                ore,
                quantity: Math.ceil(quantity / 100) * 100,
            });
        }
    }
});
</script>

<template>
    <Head title="Solver" />
    <div class="page-solver">
        Solver Page
        <!-- {{ ores }} -->
        <pre>{{ results }}</pre>

        List of Ores, rounded
        <pre>{{ listOres }}</pre>
    </div>
</template>
