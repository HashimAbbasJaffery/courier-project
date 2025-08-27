<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    label: String,
    type: String,
    name: String,
    placeholder: String,
    modelValue: String,
    length: {
        default: 6,
        type: Number,
        required: true
    },
    isTextArea: {
        type: Boolean,
        default: false
    }
});

const emits = defineEmits(['update:modelValue']);

const value = ref(props.modelValue || '');

watch(() => props.modelValue, (newValue) => {
    value.value = newValue || '';
});

const handleInput = (event) => {
    value.value = event.target.value;
    emits('update:modelValue', event.target.value);
};
</script>

<template>
    <div :class="`col-md-${length}`" v-if="!isTextArea">
        <div class="form-floating form-floating-outline">
            <input 
                :value="value"
                @input="handleInput"
                :type="type" 
                :name="name" 
                class="form-control" 
                :placeholder="placeholder" 
                required
            >
            <label>{{ label }}</label>
        </div>
    </div>
    <div class="col-md-12" v-else>
        <div class="form-floating form-floating-outline">
            <textarea 
                :value="value"
                @input="handleInput"
                :name="name" 
                class="form-control" 
                :placeholder="placeholder"
            ></textarea>
            <label>{{ label }}</label>
        </div>
    </div>
</template>
