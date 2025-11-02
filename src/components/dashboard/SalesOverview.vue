<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

const currentDate = new Date();
const currentYear = currentDate.getFullYear() + 543;

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user'; 
// แก้ไข TSX ambiguity: เพิ่มเครื่องหมาย , ใน Generic Type เพื่อให้ TypeScript แยกแยะได้ว่าไม่ใช่ JSX
const summaryData = ref<Record<string, Record<string, Record<string, number>>> >({}); 

const priceRanges = [
  'ไม่เกิน 2.50 ล้านบาท',
  '2.51 - 5 ล้านบาท',
  '5.01 - 10 ล้านบาท',
  '10.01 - 20 ล้านบาท',
  '20.01 ล้านขึ้นไป'
];

const monthsThai = [
    "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
    "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
];

const monthsThaiFull = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
];


const chartMonths = computed((): Array<{label: string, year: string, month: string, dataKey: string}> => {
    const monthsArray = [];
    const numMonths = 4; // ต้องการย้อนหลัง 4 เดือน
    const currentMonthIndex = currentDate.getMonth(); 
    
    for (let i = numMonths - 1; i >= 0; i--) {
     
        const date = new Date(currentDate.getFullYear(), currentMonthIndex - i, 1);
        const year = date.getFullYear() + 543;
        const monthNum = date.getMonth(); 
        const monthKey = (monthNum + 1).toString(); 
        const yearKey = year.toString(); 
        
        monthsArray.push({
            label: `${monthsThai[monthNum]}/${yearKey.slice(-2)}`, 
            year: yearKey,
            month: monthKey,
            dataKey: `${yearKey}-${monthKey}` 
        });
    }
    return monthsArray;
});


const chartTitleRange = computed(() => {
    if (chartMonths.value.length === 0) return 'สรุปยอดเซ็นสัญญา';

    const firstMonth = chartMonths.value[0];
    const lastMonth = chartMonths.value[chartMonths.value.length - 1];

    
    const startMonthIndex = parseInt(firstMonth.month) - 1;
    const endMonthIndex = parseInt(lastMonth.month) - 1;
    
    const startMonthName = monthsThaiFull[startMonthIndex];
    const endMonthName = monthsThaiFull[endMonthIndex];
    const endYear = lastMonth.year;

    // ตรวจสอบกรณีที่ช่วงเวลาคาบเกี่ยวปี (เช่น ธ.ค. 2567 - ม.ค. 2568)
    if (firstMonth.year !== lastMonth.year) {

        return `สรุปยอดเซ็นสัญญา (${startMonthName} ${firstMonth.year} - ${endMonthName} ${endYear})`;
    } 
    
   
    return `สรุปยอดเซ็นสัญญา (${startMonthName} - ${endMonthName} ${endYear})`;
});


const isLoading = ref(true); 

const fetchSummary = async () => {
    if (userRole !== 'admin' && userRole !== 'master' && !userId) return;

    isLoading.value = true;


    const latestMonth = chartMonths.value[3];
    const firstMonth = chartMonths.value[0];


    try {
        const res = await fetch(`https://uat.hba-sales.org/backend/sales_overview.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                role: userRole,
               
                start_month: firstMonth.month, 
                start_year: firstMonth.year,
                end_month: latestMonth.month,
                end_year: latestMonth.year,
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
    const labels = ['จำนวนหลัง (หลัง)', 'มูลค่ารวม (ล้านบาท)', 'พื้นที่ใช้สอย (ตร.ม.)'];

    return categories.map((cat, index) => ({
        name: labels[index],
        data: chartMonths.value.map(monthObj =>
            priceRanges.reduce((sum, range) => {
          
                let value = (summaryData.value[monthObj.dataKey]?.[range]?.[cat] || 0);
             
                if (cat === 'value') {
                    value = value / 1000000;
                }

                return sum + value;
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
      horizontal: false, columnWidth: '60%', endingShape: 'flat', borderRadius: 4,
      dataLabels: { position: 'top' }
    }
  },
  dataLabels: { enabled: false },
  stroke: { show: true, width: 5, colors: ['transparent'] },
  xaxis: {
    type: 'category',
  
    categories: chartMonths.value.map(m => m.label),
    axisTicks: { show: false }, axisBorder: { show: false },
    labels: { style: { colors: '#a1aab2' } }
  },
  yaxis: { 
    labels: { 
        style: { colors: '#a1aab2' },
       
        formatter: (val: number) => val.toFixed(0)
    } 
  },
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
              <h3 class="card-title">{{ chartTitleRange }}</h3>
              <h5 class="card-subtitle">จำนวนหลัง /หน่วย: ล้านบาท / พื้นที่ใช้สอย</h5>
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
