<script setup lang="ts">
import Quarterly_Value_Report from '@/components/dashboard/Quarterly_Value_Report.vue';
import Regional_report from '@/components/dashboard/Regional_report.vue';
// --- NEW IMPORTS ---
import MonthContractTableMaster from '@/components/shared/MonthContractTableMaster.vue';
import MemberStatusReport from '@/components/dashboard/MemberStatusReport.vue';
// -------------------
import { ref, computed, onMounted, watch } from 'vue';
const tab = ref('monthly');

interface SummaryData {
    yearly_data: Record<string, Record<string, number>>;
    monthly_data: Record<string, Record<number, Record<string, number>>>;
    region_data?: Record<string, any>;
    quarterly_data?: Record<string, Record<number, Record<string, number>>>;
}

const selectyear = ref<string[]>([]);
const selectMonths = ref<string[]>([]);

const year = ['2568', '2567', '2566', '2565'];
const Months = [
    'มกราคม',
    'กุมภาพันธ์',
    'มีนาคม',
    'เมษายน',
    'พฤษภาคม',
    'มิถุนายน',
    'กรกฎาคม',
    'สิงหาคม',
    'กันยายน',
    'ตุลาคม',
    'พฤศจิกายน',
    'ธันวาคม'
];

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';
const summaryData = ref<SummaryData | null>(null);

const chartSeries = ref<any[]>([]);

const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป', 'รวม'];

const monthMap: { [key: string]: number } = {
    มกราคม: 1,
    กุมภาพันธ์: 2,
    มีนาคม: 3,
    เมษายน: 4,
    พฤษภาคม: 5,
    มิถุนายน: 6,
    กรกฎาคม: 7,
    สิงหาคม: 8,
    กันยายน: 9,
    ตุลาคม: 10,
    พฤศจิกายน: 11,
    ธันวาคม: 12
};

const fetchSummary = async () => {
  
    if (!selectyear.value || selectyear.value.length === 0) {
        console.error('Please select at least one year.');
   
        return; 
    }

    const data: any = {
        user_id: userId,
        buddhist_year: selectyear.value,
        role: userRole
    };

    if (selectMonths.value.length > 0) {
        data.months = selectMonths.value.map((month: string) => monthMap[month] || null);
    }

    console.log('Sending data to backend:', data);

    try {
        const res = await fetch('https://uat.hba-sales.org/backend/repost_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const textResponse = await res.text();
        console.log('Raw response:', textResponse);

        if (!textResponse) {
            throw new Error('Empty response from backend');
        }

        const jsonData = JSON.parse(textResponse);
        console.log('Parsed JSON Data:', jsonData);
        if (jsonData.error) {
            console.error(jsonData.error);
            return;
        }

        summaryData.value = jsonData;

    
        updateChartData(jsonData);

    } catch (err) {
        console.error('❌ Error fetching summary:', err);
    }
};

const updateChartData = (data: SummaryData) => {
    const finalSeries: any[] = [];
    const dataForAverageCalc: number[][] = [];
    let finalCategories: string[] = categoryOrder; // --- FIX: แกน X ควรเป็น categoryOrder เสมอ ---

    const selectedYears = selectyear.value;
    const selectedMonths = selectMonths.value;

    if (selectedYears.length === 1 && selectedMonths.length > 1) {
        // กรณี: เทียบเดือน (ในป
        // เดียวกัน)
        finalCategories = categoryOrder; // <--- นี่คือจุดที่แก้ไข Bug
        const selectedYear = selectedYears[0];

        selectedMonths.forEach((month) => {
            const monthIndex = monthMap[month];
            const monthlyData = categoryOrder.map((category) => {
                const monthData = data.monthly_data[selectedYear]?.[monthIndex];
                return monthData && monthData[category] !== undefined ? monthData[category] : 0;
            });
            
            dataForAverageCalc.push(monthlyData);
            finalSeries.push({
                name: `${Months[monthIndex - 1]} ${selectedYear}`,
                type: 'column',
                data: monthlyData
            });
        });
    } else if (selectedYears.length > 1 && selectedMonths.length > 1) {
        // กรณี: เทียบปีและเดือน (โค้ดเดิมของคุณจำกัดที่ 2 ซีรีส์)
        finalCategories = categoryOrder;
        const firstYear = selectedYears[0];
        const secondYear = selectedYears[1];

        selectedMonths.forEach((month, index) => {
            const monthIndex = monthMap[month];
            if (index === 0) {
                const monthlyData1 = categoryOrder.map((category) => (data.monthly_data[firstYear]?.[monthIndex]?.[category] || 0));
                dataForAverageCalc.push(monthlyData1);
                finalSeries.push({ name: `${Months[monthIndex - 1]} ${firstYear}`, type: 'column', data: monthlyData1 });
            }
            if (index === 1 && secondYear) {
                const monthlyData2 = categoryOrder.map((category) => (data.monthly_data[secondYear]?.[monthIndex]?.[category] || 0));
                dataForAverageCalc.push(monthlyData2);
                finalSeries.push({ name: `${Months[monthIndex - 1]} ${secondYear}`, type: 'column', data: monthlyData2 });
            }
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 0) { 
        // กรณี: สรุปรายปี 1 ปี
        finalCategories = categoryOrder;
        const yearlyData = categoryOrder.map((category) => (data.yearly_data[selectedYears[0]]?.[category] || 0));
        dataForAverageCalc.push(yearlyData);
        finalSeries.push({ name: `ปี ${selectedYears[0]}`, type: 'column', data: yearlyData });
    } else if (selectMonths.value.length === 1 && selectedYears.length >= 2) { 
        // กรณี: เทียบปี (ในเดือนเดียวกัน)
        finalCategories = categoryOrder;
        const monthIndex = monthMap[selectMonths.value[0]];
        
        selectedYears.forEach((year) => {
            const monthlyData = categoryOrder.map((category) => (data.monthly_data[year]?.[monthIndex]?.[category] || 0));
            dataForAverageCalc.push(monthlyData);
            finalSeries.push({ name: `${selectMonths.value[0]} ${year}`, type: 'column', data: monthlyData });
        });
    } else if (selectMonths.value.length === 0 && selectedYears.length > 1) { 
        // กรณี: เทียบปี (สรุปรายปี)
        finalCategories = categoryOrder;
        selectedYears.forEach((year) => {
            const yearlyData = categoryOrder.map((category) => (data.yearly_data[year]?.[category] || 0));
            dataForAverageCalc.push(yearlyData);
            finalSeries.push({ name: `ปี ${year}`, type: 'column', data: yearlyData });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 1) { 
        // กรณี: สรุป 1 เดือน 1 ปี
        finalCategories = categoryOrder;
        const selectedYear = selectedYears[0];
        const monthIndex = monthMap[selectedMonths[0]];
        
        const monthlyData = categoryOrder.map((category) => (data.monthly_data[selectedYear]?.[monthIndex]?.[category] || 0));
        dataForAverageCalc.push(monthlyData);
        finalSeries.push({ name: `${selectedMonths[0]} ${selectedYear}`, type: 'column', data: monthlyData });
    }

    // --- 🚀 LOGIC ใหม่: จำกัด 3 แท่ง และ เพิ่มเส้นค่าเฉลี่ย ---

    // 1. จำกัดกราฟแท่งให้เหลือสูงสุด 3 แท่ง
    const limitedBarSeries = finalSeries.slice(0, 3);
    
    // 2. คำนวณค่าเฉลี่ยจากข้อมูล "ทั้งหมด" ที่เลือกมา (dataForAverageCalc)
    const averageData: number[] = [];
    const numSeries = dataForAverageCalc.length;
    const numCategories = categoryOrder.length;

    if (numSeries > 0) {
        for (let i = 0; i < numCategories; i++) { // วนลูปตามแกน X (Category)
            let sum = 0;
            for (let j = 0; j < numSeries; j++) { // วนลูปตามซีรีส์ (Months/Years)
                sum += (dataForAverageCalc[j][i] || 0);
            }
            averageData.push(Math.round(sum / numSeries)); // หาค่าเฉลี่ยของ Category นั้น
        }
        
       // ... (ส่วนคำนวณ averageData)

        // 3. เพิ่มซีรีส์ "ค่าเฉลี่ย" เป็นกราฟเส้น
        limitedBarSeries.push({
            name: 'ค่าเฉลี่ย',
            type: 'line',
            data: averageData,
          
            
        });
    }
    
    // 4. อัปเดตข้อมูลกราฟ
    chartSeries.value = limitedBarSeries;

    };
    
   
const chartSubtitle = computed(() => {
    if (selectMonths.value.length === 1 && selectyear.value.length === 1) {
        const selectedMonth = selectMonths.value[0];
        const selectedYear = selectyear.value[0];
        return `เดือน ${selectedMonth} ปี ${selectedYear}`;
    } else if (selectMonths.value.length > 1 && selectyear.value.length === 1) {
        const months = selectMonths.value.join(' - ');
        const selectedYear = selectyear.value[0];
        return `เดือน ${months} ปี ${selectedYear}`;
    } else if (selectMonths.value.length === 1 && selectyear.value.length > 1) {
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        const selectedMonth = selectMonths.value[0];
        return `เดือน ${selectedMonth} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectMonths.value.length > 1 && selectyear.value.length > 1) {
        const months = selectMonths.value.join(' - ');
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        return `เดือน ${months} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectyear.value.length === 1 && selectMonths.value.length === 0) { 
        const selectedYear = selectyear.value[0];
        return `สรุปรายปี ${selectedYear}`;
    } else if (selectyear.value.length > 1 && selectMonths.value.length === 0) { 
        const firstYear = selectyear.value[0];
        const lastYear = selectyear.value[selectyear.value.length - 1];
        return `เปรียบเทียบรายปี ${firstYear} - ปี ${lastYear}`;
    }

    return 'กรุณาเลือกข้อมูลเพื่อแสดงผล';
});

watch(
    [selectyear, selectMonths],
    () => {
        fetchSummary();
    },
    { immediate: true } 
);


const chartOptions = ref({
    chart: {
        height: 350,
        type: 'line', // <--- type 'line' ถูกต้องแล้วสำหรับ Mixed Chart
        stacked: false,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: {
            show: true,
            tools: {
                download: true
            }
        },
        zoom: {
            enabled: true,
            type: 'xy'
        }
    },
   plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '50%',
            
        },
        line: {
           
            curve: 'smooth'
        }
    },
   dataLabels: {
        enabled: true,
        position: 'top',
        offsetY: -15, 
        style: {
            fontSize: '10px'
        },
        formatter: (value: number | null | undefined) => {
            if (value === null || value === undefined) {
                return '';
            }
            return value >= 1000 ? value.toLocaleString('th-TH') : value.toString();
        }
    },
    stroke: {
        width: 2, 
        curve: 'smooth'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
    xaxis: {
        categories: categoryOrder,
        labels: {
            style: {
                fontSize: '12px',
                colors: '#6c757d'
            }
        },
        tickPlacement: 'on'
    },
    tooltip: {
        fixed: {
            enabled: true,
            position: 'topLeft',
            offsetY: 0,
            offsetX: 0
        }
    },
    legend: {
        horizontalAlign: 'center',
        offsetX: 0
    }
});

</script>

<template>
    <v-app>
        <v-container>
            <v-row>
                <v-col cols="12">
                    <div class="mt-3 mb-6">
                        <div class="d-flex justify-space-between">
                            <div class="d-flex py-0 align-center">
                                <div>
                                    <h3 class="text-h5 card-title">รายงานเปรียบเทียบยอดเซ็นสัญญา</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                          

                            <v-tabs v-model="tab" color="primary" grow>
                                <v-tab value="monthly">
                                    <v-icon start>mdi-calendar-month</v-icon>
                                    เปรียบเทียบรายเดือน
                                </v-tab>
                            </v-tabs>

                            <v-window v-model="tab" class="pt-5">
                                <v-window-item value="monthly">
                                    <v-container fluid class="pa-0">
                                        <v-alert density="compact" variant="tonal" color="info" class="mb-4">
                                            <b>วิธีใช้งาน:</b><br />
                                            - <b>เทียบเดือน (ในป
                                                เดียวกัน):</b> เลือก 1 ปี และเลือกหลายเดือน<br />
                                            - <b>เทียบปี (ในเดือนเดียวกัน):</b> เลือกหลายปี และเลือก 1 เดือน
                                        </v-alert>

                                        <v-row>
                                            <v-col cols="12" md="6">
                                                <v-combobox
                                                    v-model="selectyear"
                                                    :items="year"
                                                    label="เลือกปี"
                                                    chips
                                                    multiple
                                                    clearable
                                                    variant="outlined"
                                                    density="comfortable"
                                                ></v-combobox>
                                            </v-col>
                                            <v-col cols="12" md="6">
                                                <v-combobox
                                                    v-model="selectMonths"
                                                    :items="Months"
                                                    label="เลือกเดือน"
                                                    chips
                                                    multiple
                                                    clearable
                                                    variant="outlined"
                                                    density="comfortable"
                                                ></v-combobox>
                                            </v-col>
                                        </v-row>
                                    </v-container>
                                </v-window-item>
                            </v-window>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟเปรียบเทียบยอดเซ็นสัญญา แยกตามมูลค่าบ้าน (รายเดือน)</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart id="chart1" type="line" :options="chartOptions" :series="chartSeries" height="350" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <MemberStatusReport />

                <MonthContractTableMaster />
                
            <Quarterly_Value_Report />

             <Regional_report />
      
            </v-row>

            
        </v-container>

    </v-app>
    
</template>