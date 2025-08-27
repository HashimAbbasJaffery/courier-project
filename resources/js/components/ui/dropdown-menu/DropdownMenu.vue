<script setup>

const emits = defineEmits(['update:modelValue']);

defineProps({
    label: String,
    name: String,
    length: {
        default: 6,
        type: Number,
        required: true
    },
    nonvalueitem: String,
    items: Array,
    valueKey: String,
    itemKey: String,
    selected: String
});

</script>
<template>
    <div :class="`col-md-${length}`">
        <div class="form-floating form-floating-outline">
            <select @change="emits('update:modelValue', $event.target.value)" :name="name" class="form-select" required>
                <option value="">{{ nonvalueitem }}</option>
                <option v-for="item in items" :selected="item[valueKey] == selected" :key="item.id" :value="item[valueKey]">{{ item[itemKey] }}</option>
            </select>
            <label>{{ label }}</label>
        </div>
        </div>
</template>
