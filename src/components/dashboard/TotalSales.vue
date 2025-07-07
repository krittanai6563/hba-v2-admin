<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';

const currentYear = new Date().getFullYear() + 543;
const selectedYear = ref(currentYear.toString());
const yearOptions = computed(() => {
    return Array.from({ length: 7 }, (_, i) => (currentYear - i).toString());
});

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user'; 

const summaryData = ref<Record<string, Record<string, number>>>({});

const priceRanges = ['ไม่เกิน 2.50 ล้านบาท',
    '2.51 - 5 ล้านบาท',
    '5.01 - 10 ล้านบาท',
    '10.01 - 20 ล้านบาท',
    '20.01 ล้านขึ้นไป'];
const dataTypes = ['unit', 'total_value', 'usable_area'];

const fetchSummary = async () => {
    if (!userId) return;
    try {
        const res = await fetch('https://c44d-2405-9800-b861-96e-d38-cc71-74cd-d0c1.ngrok-free.app/package/backend/get_contract_summary.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                buddhist_year: selectedYear.value,
                quarter: selectedQuarter.value === 'ทั้งปี' ? null : selectedQuarter.value,
                role: userRole
            })

        });
        const data = await res.json();
        console.log('✅ ข้อมูลที่ได้จาก backend:', data);
        summaryData.value = data;
    } catch (err) {
        console.error('❌ Error fetching summary:', err);
    }
};


const selectedQuarter = ref('ทั้งปี'); 
const quarterOptions = ['ทั้งปี', 'ไตรมาส 1', 'ไตรมาส 2', 'ไตรมาส 3', 'ไตรมาส 4'];



watch([selectedYear, selectedQuarter], fetchSummary, { immediate: true });

onMounted(fetchSummary);

const getValue = (type: string, range: string) => {
    return summaryData.value?.[type]?.[range] || 0;
};


const chartSeries = ref<number[] | null>(null);

watch(summaryData, () => {
    chartSeries.value = priceRanges.map(range => getValue('unit', range));
});

const chartLabels = priceRanges;
defineExpose({ chartLabels });


const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 250,
        fontFamily: 'inherit',
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
            animateGradually: {
                enabled: true,
                delay: 150
            },
            dynamicAnimation: {
                enabled: true,
                speed: 500
            }
        }
    },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    plotOptions: {
        pie: {
            expandOnClick: true,
            donut: {
                size: '83',
                labels: {
                    show: true,
                    name: { show: true, offsetY: 7 },
                    value: { show: false },
                    total: {
                        show: true,
                        color: '#a1aab2',
                        fontSize: '13px',
                        label: 'จำนวนทั้งหมด'
                    }
                }
            }
        }
    },
    colors: [
        'rgba(var(--v-theme-primary))',
        'rgba(var(--v-theme-secondary))',
        '#ecf0f2',
        'rgba(var(--v-theme-purple))',
        '#FDD835' 
    ],
    tooltip: {
        show: true,
        fillSeriesColor: false
    },
    legend: { show: false },
    responsive: [
        {
            breakpoint: 1025,
            options: { chart: { height: 270 } }
        },
        {
            breakpoint: 426,
            options: { chart: { height: 250 } }
        }
    ]
}));

const totalUnit = computed(() => {
    let sum = 0;
    const data = summaryData.value.unit || {};
    for (const key in data) {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
            sum += data[key];
        }
    }
    return sum;
});

const totalValue = computed(() => {
    let sum = 0;
    const data = summaryData.value.total_value || {};
    for (const key in data) {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
            sum += data[key];
        }
    }
    return sum;
});

const totalArea = computed(() => {
    let sum = 0;
    const data = summaryData.value.usable_area || {};
    for (const key in data) {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
            sum += data[key];
        }
    }
    return sum;
});



const chartOptionsWithSeries = computed(() => ({
  ...chartOptions.value,
  labels: chartLabels,
  series: chartSeries.value || [],
}));



</script>

<template>

<template>

</template>

    <v-row>
        <v-col >
            <VCard elevation="10">
                <v-card-text>
                    <div>
                        <h3 class="card-title mb-1">มูลค่ายอดเซ็นสัญญา</h3>
                    </div>
                    <div class="mt-5 pt-5 position-relative d-flex justify-center">
                        <div v-if="!chartSeries || chartSeries.every(v => v === 0)">
                            <p class="text-subtitle-1 text-grey">รอประมวลผล...</p>
                        </div>
                        <apexchart v-else type="donut" height="260" :options="chartOptionsWithSeries" :series="chartSeries" />

                    </div>
                </v-card-text>

                <div class="d-flex align-center justify-center border-t py-2 ga-1 flex-wrap"
                    v-if="chartSeries && chartSeries.some(v => v > 0)">
                    <div v-for="(range, index) in chartLabels" :key="range" class="d-flex align-center px-2">
                        <span class="pa-1 rounded-circle me-2"
                            :style="{ backgroundColor: chartOptions.colors[index] }"></span>
                        <span class="text-p" style="font-size: 10px;">{{ range }}</span>
                    </div>
                </div>
            </VCard>

        </v-col>

      





    </v-row>
</template>

<style scoped>
.text-subtitle-1 {
    font-size: 14px;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
