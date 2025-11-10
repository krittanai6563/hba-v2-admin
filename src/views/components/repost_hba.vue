<script setup lang="ts">
import Quarterly_Value_Report from '@/components/dashboard/Quarterly_Value_Report.vue';
import Regional_report from '@/components/dashboard/Regional_report.vue';

// -------------------
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs'; // Import useDate for finding current month

const date = useDate(); // Initialize date utilities
const tab = ref('monthly');

interface SummaryData {
    // ข้อมูลจาก API มีเพียงมูลค่ารวมเป็นตัวเลข (number) เท่านั้น
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

// มูลค่าบ้านตามลำดับ (รวม 'รวม' ด้วยสำหรับกราฟ)
const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป', 'รวม'];
// มูลค่าบ้านสำหรับคำนวณ (ไม่รวม 'รวม')
const valueCategories = categoryOrder.filter(cat => cat !== 'รวม'); 

// นิยามแถว Metric ที่ต้องการแสดงผลในตาราง
const metricRows = [
    { key: 'units', name: 'จำนวนหลัง', format: (v: number) => v.toLocaleString('th-TH') },
    { key: 'value', name: 'มูลค่ารวม (บาท)', format: (v: number) => v.toLocaleString('th-TH', { maximumFractionDigits: 0 }) },
    { key: 'area', name: 'พื้นที่ใช้สอย (ตร.ม.)', format: (v: number) => v.toLocaleString('th-TH', { maximumFractionDigits: 0 }) },
    { key: 'avg_price_sqm', name: 'ราคาเฉลี่ย/ตร.ม.', format: (v: number) => v.toLocaleString('th-TH', { maximumFractionDigits: 0 }) },
];


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

// Utility to get current Buddhist year (string) and month (index 1-12)
const getCurrentPeriod = () => {
    const today = new Date();
    // FIX 1: Change 'to' to 'format'
    // FIX 2: Remove `.substring(2, 6)` to ensure a 4-digit year (e.g., '2568') is returned for comparison against the `year` array.
    const currentBuddhistYear = (date as any).format(today, 'yyyy', { locale: 'th-TH' });
    const currentMonthIndex = today.getMonth() + 1; // 1-12
    return { currentBuddhistYear, currentMonthIndex };
};


const fetchSummary = async () => {
    const { currentBuddhistYear } = getCurrentPeriod();

    // 🚀 NEW LOGIC: Default to current year if nothing is selected
    if (!selectyear.value || selectyear.value.length === 0) {
        if (year.includes(currentBuddhistYear)) {
             selectyear.value = [currentBuddhistYear];
             // Continue to fetch
        } else {
             console.error('Please select at least one year.');
             summaryData.value = null;
             chartSeries.value = [];
             return; 
        }
    }

    const data: any = {
        user_id: userId,
        buddhist_year: selectyear.value,
        role: userRole
    };

    // Note: API still fetches data based on selected filters only (no automatic default month filtering for API call)
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
    let finalCategories: string[] = categoryOrder; 

    const selectedYears = selectyear.value;
    const selectedMonths = selectMonths.value;

    // ... (Chart logic remains the same) ...
    if (selectedYears.length === 1 && selectedMonths.length > 1) {
        // กรณี: เทียบเดือน (ในปีเดียวกัน)
        finalCategories = categoryOrder; 
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

    // --- LOGIC: จำกัด 3 แท่ง และ เพิ่มเส้นค่าเฉลี่ย ---
    const limitedBarSeries = finalSeries.slice(0, 3);
    const averageData: number[] = [];
    const numSeries = dataForAverageCalc.length;
    const numCategories = categoryOrder.length; 

    if (numSeries > 0) {
        for (let i = 0; i < numCategories; i++) { 
            let sum = 0;
            for (let j = 0; j < numSeries; j++) { 
                sum += (dataForAverageCalc[j][i] || 0);
            }
            averageData.push(Math.round(sum / numSeries)); 
        }
        
        limitedBarSeries.push({
            name: 'ค่าเฉลี่ย',
            type: 'line',
            data: averageData,
        });
    }
    
    chartSeries.value = limitedBarSeries;
};
    
   
const chartSubtitle = computed(() => {
    // Logic remains mostly the same for explicit selections
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

    // 🚀 NEW LOGIC: Default when 0 selection
    const { currentBuddhistYear } = getCurrentPeriod();
    const currentMonthName = Months[new Date().getMonth()];
    
    // ตรวจสอบว่า selectyear ถูกกำหนดค่าเริ่มต้นโดย fetchSummary แล้วหรือไม่
    if (selectyear.value.length === 1 && selectyear.value[0] === currentBuddhistYear) {
         return `สรุปยอดเซ็นสัญญา ถึงเดือน ${currentMonthName} ปี ${currentBuddhistYear}`;
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
    // ... (chartOptions remains the same) ...
    chart: {
        height: 350,
        type: 'line', 
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


// 🚀 LOGIC ใหม่: Computed Property สำหรับกำหนดช่วงเวลาแสดงผลในตาราง (Column Headers)
const tablePeriods = computed(() => {
    const selectedYears = selectyear.value;
    const selectedMonths = selectMonths.value;
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    
    let periods: { key: string, label: string, year: string, monthIndex?: number }[] = [];

    // --- Case A: Months are explicitly selected (Compare Months or Compare Years by Month) ---
    if (selectedMonths.length > 0) {
        selectedYears.forEach(year => {
            selectedMonths.forEach(monthName => {
                const monthIndex = monthMap[monthName];
                if (monthIndex) {
                    periods.push({
                        key: `M${monthIndex}Y${year}`,
                        label: `${monthName} ${year}`,
                        year: year,
                        monthIndex: monthIndex
                    });
                }
            });
        });
    } 
    // --- Case B: No Months selected ---
    else {
        // B1: Single Year selected (current year) AND no month -> Default to Jan - Current Month of current year
        if (selectedYears.length === 1 && selectedYears[0] === currentBuddhistYear) {
            // Display Jan - Current Month
             for(let i = 1; i <= currentMonthIndex; i++) {
                 periods.push({
                    key: `M${i}Y${currentBuddhistYear}`,
                    label: `${Months[i-1]} ${currentBuddhistYear}`,
                    year: currentBuddhistYear,
                    monthIndex: i
                });
            }
        } 
        // B2: Other cases (Multiple years selected or single past year selected with no month) -> Yearly Summary
        else if (selectedYears.length > 0) {
            selectedYears.forEach(year => {
                periods.push({
                    key: `Y${year}`,
                    label: `สรุปปี ${year}`,
                    year: year
                });
            });
        }
        // B3: No Year and No Month selected (Uses fetchSummary default logic)
        else {
             if (year.includes(currentBuddhistYear)) { 
                 for(let i = 1; i <= currentMonthIndex; i++) {
                     periods.push({
                        key: `M${i}Y${currentBuddhistYear}`,
                        label: `${Months[i-1]} ${currentBuddhistYear}`,
                        year: currentBuddhistYear,
                        monthIndex: i
                    });
                }
            }
        }
    }

    // --- Add Grand Total Column ---
    let finalPeriods = periods; 
    
    // Add 'รวมทุกช่วง' column if we are comparing multiple columns
    if (finalPeriods.length > 1) { 
         finalPeriods.push({
            key: 'TOTAL_PERIODS',
            label: 'รวมทุกช่วง',
            year: 'TOTAL',
            monthIndex: 0
        });
    }

    return finalPeriods;
});

// Interfaces for Table Data structure
interface TableCellData {
    [key: string]: number; // Key is the period key (e.g., 'M1Y2567', 'TOTAL_PERIODS')
}
interface TableRow {
    metricKey: string; // 'units', 'value', 'area', 'avg_price_sqm'
    metricName: string;
    format: (v: number) => string;
    data: TableCellData;
}
interface TableCategory {
    categoryName: string;
    rows: TableRow[];
}

// 🚀 LOGIC ใหม่: Computed Property สำหรับตารางสรุปมูลค่าบ้าน (โครงสร้าง Nested Rows/Dynamic Columns)
const monthlyReportTableData = computed<TableCategory[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';
    
    // แยก periods ที่ไม่ใช่ 'รวมทุกช่วง' ออกมาก่อน เพื่อใช้ดึงข้อมูล
    const dataPeriods = currentPeriods.filter(p => p.key !== grandTotalPeriodKey); 
    
    // --- Step 1: ดึงข้อมูล 'value' จาก API ตาม Category และ Period ---
    const valueDataByPeriodAndCategory: Record<string, Record<string, number>> = {}; 
    const categoriesToMap = [...valueCategories, 'รวม']; 

    categoriesToMap.forEach(cat => {
        valueDataByPeriodAndCategory[cat] = {};
        dataPeriods.forEach(p => {
             let value = 0;
            // Check if it's a monthly period or a yearly period key
            if (p.monthIndex && p.monthIndex !== 0) {
                // Monthly Data: ใช้ monthly_data
                value = summaryData.value?.monthly_data[p.year]?.[p.monthIndex]?.[cat] || 0;
            } else if (!p.monthIndex) {
                // Yearly Data: ใช้ yearly_data 
                value = summaryData.value?.yearly_data[p.year]?.[cat] || 0;
            }

            valueDataByPeriodAndCategory[cat][p.key] = value; 
        });
    });

    // --- Step 2: จัดโครงสร้างผลลัพธ์สุดท้ายและคำนวณ 'รวม' ---
    const finalTable: TableCategory[] = [];
    const allCategories = [...valueCategories, 'รวม'];

    allCategories.forEach(categoryName => {
        const categoryData: TableCategory = {
            categoryName: categoryName,
            rows: []
        };

        metricRows.forEach(metric => {
            const row: TableRow = {
                metricKey: metric.key,
                metricName: metric.name,
                format: metric.format,
                data: {}
            };
            
            let totalMetricValue = 0; // รวมค่า Metric นี้ตลอดทุก Period

            dataPeriods.forEach(p => {
                const periodKey = p.key;
                let metricValue = 0;

                if (metric.key === 'value') {
                     let rawValue = valueDataByPeriodAndCategory[categoryName][periodKey] || 0;
                     if (categoryName === 'รวม') {
                        // สำหรับแถว 'รวม' (ในแต่ละคอลัมน์): คำนวณผลรวมของ 'value' จากทุก valueCategories ใน Period นี้
                        rawValue = valueCategories.reduce((sum, cat) => {
                            return sum + (valueDataByPeriodAndCategory[cat][periodKey] || 0);
                        }, 0);
                    } 
                    metricValue = rawValue;
                } 
                // สำหรับ Metric อื่นๆ (units, area, avg_price_sqm) ให้แสดงเป็น 0/N/A
                else {
                    metricValue = 0; 
                }
                
                row.data[periodKey] = metricValue;
                totalMetricValue += metricValue; 
            }); 

            // Add 'รวมทุกช่วง' column
            if (currentPeriods.find(p => p.key === grandTotalPeriodKey)) {
                row.data[grandTotalPeriodKey] = totalMetricValue;
            }

            categoryData.rows.push(row);
        });
        finalTable.push(categoryData);
    });
    
    return finalTable;
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
                                            - <b>ไม่เลือกปี/เดือน:</b> แสดงข้อมูลของปีปัจจุบันถึงเดือนปัจจุบัน<br />
                                            - <b>เทียบเดือน (ในปีเดียวกัน):</b> เลือก 1 ปี และเลือกหลายเดือน<br />
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
                
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสรุปยอดเซ็นสัญญา แยกตามมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            
                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 150px;">ช่วงมูลค่าบ้าน</th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 150px;">รายละเอียด</th>
                                        <th :colspan="tablePeriods.length" class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th 
                                            v-for="period in tablePeriods" 
                                            :key="period.key" 
                                            class="text-right text-subtitle-1" 
                                            :class="{'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key, 'text-primary': period.key === 'TOTAL_PERIODS'}"
                                            style="min-width: 120px;"
                                        >
                                            {{ period.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="monthlyReportTableData.length > 0">
                                        <template v-for="(category, catIndex) in monthlyReportTableData" :key="category.categoryName">
                                            <tr 
                                                v-for="(row, rowIndex) in category.rows" 
                                                :key="`${category.categoryName}-${row.metricKey}`"
                                                :class="{ 
                                                    'bg-blue-grey-lighten-5': category.categoryName === 'รวม',
                                                    'border-t': rowIndex === 0,
                                                }"
                                            >
                                                <td v-if="rowIndex === 0" 
                                                    :rowspan="category.rows.length"
                                                    class="text-left font-weight-bold text-subtitle-2 border-e"
                                                    :class="{'text-primary': category.categoryName === 'รวม'}"
                                                >
                                                    {{ category.categoryName }}
                                                </td>

                                                <td class="text-left text-caption border-e">{{ row.metricName }}</td>
                                                
                                                <td v-for="period in tablePeriods" 
                                                    :key="period.key"
                                                    class="text-right text-subtitle-2"
                                                    :class="{'text-primary font-weight-bold': category.categoryName === 'รวม' && row.metricKey === 'value', 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key}"
                                                >
                                                    {{ row.metricKey === 'value' && (row.data[period.key] || 0) != 0 ? row.format(row.data[period.key] || 0) : '-' }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 2" class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                                    </tr>
                                </tbody>
                            </v-table>
                            <p class="text-caption mt-4">
                                ⚠️ <b>หมายเหตุ:</b> ข้อมูล **จำนวนหลัง**, **พื้นที่ใช้สอย**, และ **ราคาเฉลี่ย/ตร.ม.** ในตารางนี้แสดงค่า `-` หรือ **0** เนื่องจากโครงสร้างข้อมูล 
                                (`summaryData.monthly_data` และ `summaryData.yearly_data`) ที่มาจาก `repost_admin.php` 
                                ในปัจจุบัน มีเพียง **มูลค่ารวม (Total Value)** เท่านั้น หากต้องการแสดงผลลัพธ์ที่สมบูรณ์ 
                                ต้องปรับปรุง API ให้ส่งข้อมูล Metric ทั้งหมดมาด้วย
                             </p>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-app>
</template>