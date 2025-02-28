<script setup>
import solver from "javascript-lp-solver";
import { Head, Link } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted } from "vue";
import { toast } from "vue3-toastify";

const props = defineProps({
    oresList: {
        type: Object,
        required: true,
    },
});
const loading = ref(false);
const working = ref(false);
const search = ref("Caracal");
const item = ref(null);
const currentStockInput = ref("");
const currentStockList = ref({});
const runs = ref(1);
const efficiency = ref(82.1);
const material_efficiency = ref(0);
const listShopping = ref(null);
const listPrices = ref({});

// Some refs
const inputSearch = ref(null);
const inputStock = ref(null);
const inputRuns = ref(null);
const inputMaterialEfficiency = ref(null);
const inputEfficiency = ref(null);

// Using axios, fetch the item from the database based on the search, call /search with a query parameter of search
const fetchItem = async () => {
    loading.value = true;
    await axios
        .get("/api/item/search", {
            params: {
                search: search.value,
            },
        })
        .then((response) => {
            item.value = response.data;
        })
        .catch((error) => {
            switch (error.response.status) {
                case 422:
                    toast.error("Please provide a valid search");
                    break;
                case 404:
                    toast.error("No item found with this search");
                    break;
                default:
                    toast.error("An error occured");
                    break;
            }
        })
        .finally(() => {
            loading.value = false;
        });
};

const solve = async () => {
    working.value = true;
    let response = await axios.get("/api/solver/ores");

    listPrices.value = response.data;

    // Make a new $listPricesWithoutExcluded that is a copy of $listPrices, and remove the ores that are in $oresToExclude
    let listPricesWithoutExcluded = { ...listPrices.value };
    // Loop over listPricesWithoutExcluded, and check if the value.id is in oresToExclude, if yes, remove it from the list
    for (const [key, value] of Object.entries(listPricesWithoutExcluded)) {
        if (oresToExclude.value.includes(value.id)) {
            delete listPricesWithoutExcluded[key];
        }
    }

    console.log(listPricesWithoutExcluded);

    let model = {
        optimize: "cost",
        opType: "min",
        constraints: manufactureMaterials.value.efficiency,
        variables: listPricesWithoutExcluded,
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

    let results = solver.Solve(model);

    listShopping.value = {};
    for (const [ore, quantity] of Object.entries(results)) {
        // Check if "ore" exist in the props.ores object, if yes, add it to listOres
        if (listPrices.value[ore]) {
            // Add the ore and quantity (rounded to the next 100) to the listOres array
            listShopping.value[ore] = Math.ceil(quantity / 100) * 100;
        }
    }

    working.value = false;
};

// Make a computed of item.manufacture_materials that is an object with the following structure : name: {min: quantity}
const manufactureMaterials = computed(() => {
    if (item.value?.bp) {
        let calculatedEfficiency = 100 - efficiency.value;
        // Round to 2 decimal places
        calculatedEfficiency = Math.round(calculatedEfficiency * 100) / 100;
        // Convert to a percentage
        calculatedEfficiency = (100 + calculatedEfficiency) / 100;

        let calculatedMaterialEfficiency = 100 - material_efficiency.value;
        // Round to 2 decimal places
        calculatedMaterialEfficiency =
            Math.round(calculatedMaterialEfficiency * 100) / 100;
        // Convert to a percentage
        calculatedMaterialEfficiency = calculatedMaterialEfficiency / 100;

        let materials = {
            base: {},
            efficiency: {},
        };
        for (const material of item.value.bp.manufacture_materials) {
            let tempQuantity = material.pivot.quantity * runs.value; // Apply the number of runs we want to make

            // Check if the current material.name is in currentStockList, and if yes, subtract the quantity available to the quantity needed
            if (currentStockList.value[material.name]) {
                tempQuantity -= currentStockList.value[material.name];
                // Make sure the quantity is positive, or 0, in case we have too much stuff, you know
                tempQuantity = Math.max(0, tempQuantity);
            }

            materials["base"][material.name] = {
                min: material.pivot.quantity * runs.value,
            };

            materials["efficiency"][material.name] = {
                min:
                    tempQuantity *
                    calculatedMaterialEfficiency * // Apply ME
                    calculatedEfficiency, // Apply Reprocessing Efficiency
            };
        }
        return materials;
    }

    return null;
});

// A function that return the price of an ore based on the name, and apply the quantity
const totalCost = computed(() => {
    let total = 0;
    for (const [ore, quantity] of Object.entries(listShopping.value)) {
        total += getPrice(ore, quantity);
    }
    return total;
});

const getPrice = (ore, quantity) => {
    if (listPrices.value[ore]) {
        return Math.ceil(listPrices.value[ore].cost * quantity);
    }
    return 0;
};

// A computed listShoppingMaterialsString that return a string with the materials needed for the shopping list based on listPrices, multiplied by the quantity
const listShoppingMaterialsString = computed(() => {
    // Loop over listPrices
    let ores = {};

    let calculatedEfficiency = 100 - efficiency.value;
    // Round to 2 decimal places
    calculatedEfficiency = Math.round(calculatedEfficiency * 100) / 100;
    // Convert to a percentage
    calculatedEfficiency = (100 + calculatedEfficiency) / 100;

    for (const [ore, quantity] of Object.entries(listShopping.value)) {
        // Check if "ore" exist in the props.ores object, if yes, add it to listOres
        if (listPrices.value[ore]) {
            let tempText = "<table>";
            // Loop over listPrices.value[ore], and add the name and quantity to the tempText, ignore [cost, m3]
            for (const [name, value] of Object.entries(listPrices.value[ore])) {
                if (name !== "cost" && name !== "m3") {
                    // Add the name and quantity to the tempText, and don't forget to multiply by the quantity from listShopping and round it up
                    tempText += `<tr>
                        <td>${name}</td>
                        <td class="text-right pl-3">
                            ${Math.ceil(
                                value * quantity * calculatedEfficiency
                            ).toLocaleString()}
                        </td>
                    </tr>`;
                }
            }

            tempText += "</table>";
            ores[ore] = tempText;
        }
    }
    return ores;
});

// Watch the currentStockInput, and split the input by line, then by tab (the last occurence is the quantity, the first is the item), and add it to the currentStockList , the key being the item name, and the value the quantity
watch(currentStockInput, (value) => {
    let stock = {};
    let lines = value.split("\n");
    for (const line of lines) {
        let parts = line.split("\t");
        if (parts.length === 2) {
            stock[parts[0]] = parseInt(parts[1]);
        }
    }
    currentStockList.value = stock;
});

// A function that copy the listShopping to the clipboard
const copyListShopping = () => {
    let text = "";
    for (const [ore, quantity] of Object.entries(listShopping.value)) {
        text += `${ore}\t${quantity}\n`;
    }
    navigator.clipboard.writeText(text);
    toast.success("Shopping List copied to your clipboard !");
};

const oresToExclude = ref([]);
const searchOres = ref(null);
const filteredOresToExclude = ref(props.oresList);
const showOresToExclude = ref(false);

// Automatically watch searchOres (input) and filter the ores based on the search
watch(searchOres, (value) => {
    filteredOresToExclude.value = props.oresList.filter((ore) =>
        ore.name.toLowerCase().includes(value.toLowerCase())
    );
});

// Watch oresToExclude, and when it changes, set a local storage item with the value
watch(oresToExclude, (value) => {
    localStorage.setItem("oresToExclude", JSON.stringify(value));
});

// On mount, check if there is a local storage item for oresToExclude, if yes, set it to the value
onMounted(() => {
    let ores = localStorage.getItem("oresToExclude");
    if (ores) {
        oresToExclude.value = JSON.parse(ores);
    }
});
</script>

<template>
    <div class="mt-12">
        <h2>BP to Compressed Ores Calculator</h2>
        <p>
            This tool will help you calculate the compressed ores you need to
            build a blueprint.
        </p>
        <p>
            To use it, simply search for the
            <a @mouseenter="inputSearch.focus()">Item</a> you want to build, set
            the number of <a @mouseenter="inputRuns.focus()">Runs</a>,
            <a @mouseenter="inputMaterialEfficiency.focus()">
                Material Efficiency
            </a>
            and
            <a @mouseenter="inputEfficiency.focus()">
                Reprocessing Efficiency </a
            >, you can also copy/paste a
            <a @mouseenter="inputStock.focus()">Stock</a> content and start the
            process.
        </p>
    </div>

    <div class="flex flex-col items-center justify-center mt-12">
        <div class="flex flex-col lg:flex-row gap-12">
            <div class="inline-flex gap-3 flex-col">
                <label>
                    <span>Item *</span>
                    <div class="inline-flex">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search for an Item"
                            @keyup.enter="fetchItem"
                            ref="inputSearch"
                        />
                        <button @click="fetchItem">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </label>
                <label>
                    <span>Stock</span>
                    <div class="inline-flex">
                        <textarea
                            v-model="currentStockInput"
                            placeholder="Copy/Paste your stock to take it into account"
                            ref="inputStock"
                        />
                    </div>
                </label>
            </div>
            <div class="inline-flex gap-3">
                <label>
                    <span>Runs</span>
                    <div class="inline-flex relative">
                        <input
                            v-model="runs"
                            type="number"
                            placeholder="How many ?"
                            ref="inputRuns"
                        />
                    </div>
                </label>
            </div>
            <div class="inline-flex gap-3 flex-col">
                <label>
                    <span>Material Efficiency</span>
                    <div class="inline-flex relative">
                        <input
                            v-model="material_efficiency"
                            type="text"
                            placeholder="Material Efficiency (in %)"
                            max="10"
                            ref="inputMaterialEfficiency"
                        />
                        <div
                            class="absolute left-0 top-0 h-full bg-white bg-opacity-5 pointer-events-none transition-all ease-linear"
                            :style="{
                                width:
                                    (Math.min(material_efficiency * 10, 100) ||
                                        0) + '%',
                            }"
                        ></div>
                    </div>
                </label>
                <label>
                    <span>Reprocessing Efficiency</span>
                    <div class="inline-flex relative">
                        <input
                            v-model="efficiency"
                            type="text"
                            placeholder="Efficiency (in %)"
                            ref="inputEfficiency"
                        />
                        <div
                            class="absolute left-0 top-0 h-full bg-white bg-opacity-5 pointer-events-none transition-all ease-linear"
                            :style="{
                                width: (Math.min(efficiency, 100) || 0) + '%',
                            }"
                        ></div>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex flex-col w-full mt-5">
            <div class="flex items-center justify-between">
                <div @click="showOresToExclude = !showOresToExclude">
                    <i
                        class="fa fa-fw"
                        :class="{
                            'fa-caret-down': showOresToExclude,
                            'fa-caret-right': !showOresToExclude,
                        }"
                    ></i>
                    <span class="pr-3"> Ores To Ignore </span>
                </div>

                <div class="flex items-center">
                    <span v-if="oresToExclude.length">
                        {{ oresToExclude.length }} / {{ oresList.length }}
                    </span>
                    <button
                        @click="
                            oresToExclude = [];
                            showOresToExclude = false;
                            searchOres = '';
                        "
                        class="py-0"
                        v-if="oresToExclude.length"
                    >
                        <i class="fa fa-fw fa-times"></i>
                    </button>

                    <input
                        v-model="searchOres"
                        type="text"
                        placeholder="Search for specific ores to ignore"
                        ref="inputOres"
                        @input="showOresToExclude = true"
                    />
                </div>
            </div>
            <div
                class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-5"
                v-if="showOresToExclude"
            >
                <label
                    :for="`ore-${ore.id}`"
                    v-for="ore in filteredOresToExclude"
                    :key="ore.id"
                    class="flex gap-2 items-center flex-row"
                >
                    <input
                        type="checkbox"
                        :id="`ore-${ore.id}`"
                        v-model="oresToExclude"
                        :value="ore.id"
                    />
                    <span class="p-0">{{ ore.name }}</span>
                </label>
            </div>
        </div>
    </div>

    <!-- LOADER -->
    <div class="flex items-center justify-center my-5" v-if="loading">
        <i class="fa fa-circle-notch fa-spin text-2xl"></i>
    </div>
    <!-- LOADER END -->

    <!-- ITEM SHOW -->
    <div
        class="flex flex-col lg:flex-row items-center justify-center mt-12 gap-12 relative"
        v-if="!loading"
    >
        <!-- ITEM -->
        <div class="flex gap-12" v-if="item">
            <div class="flex flex-col">
                <h2 class="text-sm uppercase font-bold">
                    {{ item.item.name }}
                </h2>
                <img
                    :src="`https://images.evetech.net/types/${item.item.id}/icon`"
                    class="eve-box p-2"
                />
            </div>
        </div>
        <!-- ITEM END -->
        <!-- ACTIONS -->
        <button class="eve-box mt-4" @click="solve" v-if="item">
            <i
                class="fa fa-fw"
                :class="{
                    'fa-industry': !working,
                    'fa-circle-notch fa-spin': working,
                }"
            ></i>
        </button>
        <!-- ACTIONS END -->
        <!-- RESULTS -->
        <div class="flex gap-12 mt-4" v-if="item && listShopping">
            <div
                class="flex flex-col eve-box p-5"
                v-if="listShopping && Object.keys(listShopping).length"
            >
                <h2
                    class="text-sm uppercase font-bold flex items-center w-full justify-between"
                >
                    <span>List of Ores</span>

                    <button @click.prevent="copyListShopping()">
                        <i class="fa fa-fw fa-copy"></i>
                    </button>
                </h2>
                <div class="flex gap-5">
                    <table>
                        <tr
                            v-for="(quantity, ore) in listShopping"
                            v-tippy
                            :data-tippy-content="
                                listShoppingMaterialsString[ore]
                            "
                        >
                            <td>{{ ore }}</td>
                            <td class="text-right">{{ quantity }}</td>
                        </tr>
                    </table>
                    <table class="text-right">
                        <tr v-for="(quantity, ore) in listShopping">
                            <td>
                                {{ getPrice(ore, quantity)?.toLocaleString() }}
                                ISK
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="text-right">
                    {{ totalCost?.toLocaleString() }} ISK
                </div>
            </div>
            <div v-else class="eve-box success p-2">
                <p>
                    Looks like you got nothing to buy, as
                    <br />your stock provide enough materials !
                </p>
            </div>
        </div>
        <!-- RESULTS END -->

        <!-- COSMECTIC -->
        <div
            class="absolute -z-10 transition-all mt-4 ease-linear flex items-center justify-center flex-row lg:flex-col"
            :class="{
                'h-1/2 lg:w-1/2 lg:ml-5': !listShopping,
                'h-full lg:w-full': listShopping,
            }"
        >
            <div
                class="max-lg:min-h-full max-lg:min-w-px lg:min-h-px lg:min-w-full bg-slate-800"
            ></div>
            <div
                class="max-lg:min-h-full max-lg:min-w-1 lg:min-h-1 lg:min-w-full my-0 lg:my-1 mx-1 lg:mx-0 bg-slate-800"
            ></div>
            <div
                class="max-lg:min-h-full max-lg:min-w-px lg:min-h-px lg:min-w-full bg-slate-800"
            ></div>
        </div>
    </div>
</template>
