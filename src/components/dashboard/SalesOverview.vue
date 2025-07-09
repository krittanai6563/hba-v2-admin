<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

const currentYear = new Date().getFullYear() + 543;

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user'; 
const summaryData = ref<Record<string, Record<string, Record<string, number>>>>({});

const priceRanges = [
  'ไม่เกิน 2.50 ล้านบาท',
  '2.51 - 5 ล้านบาท',
  '5.01 - 10 ล้านบาท',
  '10.01 - 20 ล้านบาท',
  '20.01 ล้านขึ้นไป'
];

const chartYears = computed(() => {
  return Array.from({ length: 4 }, (_, i) => (currentYear - (3 - i)).toString());
});

const isLoading = ref(true); 

const fetchSummary = async () => {
if (userRole !== 'admin' && userRole !== 'master' && !userId) return;

  isLoading.value = true;

  try {
    const res = await fetch(`https://6e9fdf451a56.ngrok-free.apppackage/backend/sales_overview.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: userId,
        role: userRole,
        year: currentYear.toString()
      })
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    summaryData.value = await res.json();
  } catch (err) {
    console.error('Error fetching summary:', err);
    summaryData.value = {}; 
  } finally {
    isLoading.value = false;
  }
};


onMounted(fetchSummary);

const chartSeries = computed(() => {
  const categories = ['unit', 'value', 'area'];
  const labels = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย'];

  return categories.map((cat, index) => ({
    name: labels[index],
    data: chartYears.value.map(year =>
      priceRanges.reduce((sum, range) => {
        return sum + (summaryData.value[year]?.[range]?.[cat] || 0);
      }, 0)
    )
  }));
});

const chartOptions = computed(() => ({
  chart: {
    fontFamily: 'inherit', type: 'bar', height: 330, foreColor: '#adb0bb',
    offsetY: 10, offsetX: -15, toolbar: { show: false }
  },
  grid: { show: true, strokeDashArray: 3, borderColor: 'rgba(0,0,0,.1)' },
  colors: ['rgba(var(--v-theme-primary))', 'rgba(var(--v-theme-secondary))'],
  plotOptions: {
    bar: {
      horizontal: false, columnWidth: '90%', endingShape: 'flat', borderRadius: 4,
      dataLabels: { position: 'top' }
    }
  },
  dataLabels: { enabled: false },
  stroke: { show: true, width: 5, colors: ['transparent'] },
  xaxis: {
    type: 'category',
    categories: chartYears.value,
    axisTicks: { show: false }, axisBorder: { show: false },
    labels: { style: { colors: '#a1aab2' } }
  },
  yaxis: { labels: { style: { colors: '#a1aab2' } } },
  fill: { opacity: 1, colors: ['rgba(var(--v-theme-primary))', 'rgba(var(--v-theme-secondary))'] },
  tooltip: { theme: 'dark' },
  legend: { show: true },
  responsive: [
    { breakpoint: 767, options: { stroke: { show: false, width: 5, colors: ['transparent'] } } }
  ]
}));
</script>


<template>
  <v-row>
    <v-col cols="12">
      <VCard elevation="10">
        <v-card-text>
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <h3 class="card-title">สรุปยอดเซ็นสัญญา ({{ chartYears[0] }} - {{ chartYears[3] }})</h3>
              <h5 class="card-subtitle">หน่วย: ล้านบาท / จำนวนหลัง / พื้นที่ใช้สอย</h5>
            </div>
          </div>
              <div v-if="isLoading" class="text-center text-secondary py-10">
           รอประมวลผลข้อมูลจากระบบ...
          </div>

         
          <apexchart
            v-else
            type="bar"
            height="350"
            :options="chartOptions"
            :series="chartSeries"
          />
        </v-card-text>
      </VCard>
    </v-col>
  </v-row>
</template>
 