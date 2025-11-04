<script setup lang="ts">
import { computed } from 'vue';

interface SummaryData {
    totalUnit: number;
    totalValue: number;
    totalArea: number;
    averagePricePerSqm: string; 
}

const props = defineProps<{
    summaryData: SummaryData;
    layout?: 'horizontal' | 'vertical'; // Prop หลักในการกำหนดทิศทาง
}>();


const formattedMetrics = computed(() => {
    const { totalUnit, totalValue, totalArea, averagePricePerSqm } = props.summaryData;

    return [
        {
            title: 'จำนวนหลัง',
            value: totalUnit.toLocaleString('th-TH'),
            unit: 'หลัง',
            icon: 'mdi-home-group',
            color: 'primary'
        },
        {
            title: 'มูลค่ารวม',
            value: totalValue.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            unit: 'บาท',
            icon: 'mdi-cash-multiple',
            color: 'secondary'
        },
        {
            title: 'พื้นที่ใช้สอย',
            value: totalArea.toLocaleString('th-TH'),
            unit: 'ตร.ม.',
            icon: 'mdi-floor-plan',
            color: 'error'
        },
        {
            title: 'ราคาเฉลี่ย/ตร.ม.',
            value: averagePricePerSqm,
            unit: 'บาท/ตร.ม.',
            icon: 'mdi-chart-bar',
            color: 'warning'
        }
    ];
});
</script>

<template>
    
    <v-row v-if="props.layout === 'horizontal' || !props.layout" class="mt-0">
        <v-col cols="12" sm="6" lg="3" v-for="(metric, index) in formattedMetrics" :key="index" class="py-2">
            <v-card elevation="2" class="h-100 pa-4" border>
                <div class="d-flex align-center">
                    <v-icon :color="metric.color" size="30" :icon="metric.icon" class="mr-4"></v-icon>
                    <div>
                        <p class="text-caption text-grey-darken-1 mb-0">{{ metric.title }} ({{ metric.unit }})</p>
                        <h4 class="text-h6 font-weight-bold">{{ metric.value }}</h4>
                    </div>
                </v-card>
            </v-col>
        </v-row>

    <div v-else-if="props.layout === 'vertical'" class="my-2">
        <v-list density="compact" lines="two" class="py-0">
            <v-list-item
                v-for="(metric, index) in formattedMetrics"
                :key="index"
                class="py-2 px-0"
                :class="{'border-b-thin': index < formattedMetrics.length - 1}"
            >
                <template v-slot:prepend>
                    <v-icon :color="metric.color" :icon="metric.icon" class="mr-4"></v-icon>
                </template>
                <v-list-item-title class="font-weight-medium text-subtitle-2">{{ metric.title }} ({{ metric.unit }})</v-list-item-title>
                <v-list-item-subtitle class="text-h6 font-weight-bold">{{ metric.value }}</v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </div>
</template>