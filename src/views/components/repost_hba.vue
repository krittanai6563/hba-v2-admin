<script setup lang="ts">
import Quarterly_Value_Report from '@/components/dashboard/Quarterly_Value_Report.vue';
import Regional_report from '@/components/dashboard/Regional_report.vue';

// -------------------
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs'; // Import useDate for finding current month

const date = useDate(); // Initialize date utilities
const tab = ref('monthly');

// Define the structure of the data object returned for each price range
interface Metrics {
    total_value: number;
    total_area: number;
    total_units: number;
    average_price_per_sqm: number;
}

interface SummaryData {
    // UPDATED: Now expects a Metrics object for each price range
    yearly_data: Record<string, Record<string, Metrics>>;
    monthly_data: Record<string, Record<number, Record<string, Metrics>>>;
    quarterly_data?: Record<string, Record<number, Record<string, Metrics>>>;
    region_data?: Record<string, any>;
    membership_data?: Record<string, any>;
}

const selectyear = ref<string[]>([]);
const selectMonths = ref<string[]>([]);
const selectQuarters = ref<string[]>([]); // 👈 NEW: State for selected quarters

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

// 👈 NEW: Quarter Definitions and Mappings
const Quarters = [
    { name: 'ไตรมาส 1', index: 1, months: [1, 2, 3], names: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม'] },
    { name: 'ไตรมาส 2', index: 2, months: [4, 5, 6], names: ['เมษายน', 'พฤษภาคม', 'มิถุนายน'] },
    { name: 'ไตรมาส 3', index: 3, months: [7, 8, 9], names: ['กรกฎาคม', 'สิงหาคม', 'กันยายน'] },
    { name: 'ไตรมาส 4', index: 4, months: [10, 11, 12], names: ['ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'] },
];
const quarterMap = Quarters.reduce((acc, q) => { acc[q.name] = q.index; return acc; }, {} as Record<string, number>);


const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';
const summaryData = ref<SummaryData | null>(null);

const chartSeries = ref<any[]>([]);

// มูลค่าบ้านตามลำดับ (รวม 'รวม' ด้วยสำหรับกราฟ)
const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป', 'รวม'];
// มูลค่าบ้านสำหรับคำนวณ (ไม่รวม 'รวม')
const valueCategories = categoryOrder.filter(cat => cat !== 'รวม'); 

// นิยามแถว Metric ที่ต้องการแสดงผลในตาราง (แก้ไขให้มีทศนิยม 2 ตำแหน่ง)
const metricRows = [
    { 
        key: 'total_units', 
        name: 'จำนวนหลัง', 
        format: (v: number) => v.toLocaleString('th-TH', { maximumFractionDigits: 0 }) 
    },
    { 
        key: 'total_value', 
        name: 'มูลค่ารวม (บาท)', 
        format: (v: number) => v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    { 
        key: 'total_area', 
        name: 'พื้นที่ใช้สอย (ตร.ม.)', 
        format: (v: number) => v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    { 
        key: 'average_price_per_sqm', 
        name: 'ราคาเฉลี่ย/ตร.ม.', 
        format: (v: number) => v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
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

    // 👈 NEW LOGIC: Priority Quarter selection for API call
    if (selectQuarters.value.length > 0) {
        data.quarters = selectQuarters.value.map((quarterName: string) => quarterMap[quarterName] || null);
        // Clear months to prevent ambiguity in API filtering, though backend ignores empty arrays anyway
        data.months = []; 
    } else if (selectMonths.value.length > 0) {
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

// 🚀 LOGIC UPDATED: To extract 'total_value' from the nested Metrics object for the chart
const updateChartData = (data: SummaryData) => {
    const finalSeries: any[] = [];
    const dataForAverageCalc: number[][] = [];
    let finalCategories: string[] = categoryOrder; 

    const selectedYears = selectyear.value;
    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value; // 👈 NEW: Use quarters filter
    const getValue = (dataObj: Metrics | undefined) => (dataObj?.total_value || 0);

    // Helper to get month indices covered by current selection (used for filtering monthlyData)
    const getSelectedMonthIndices = () => {
        if (selectedQuarters.length > 0) {
            let months: number[] = [];
            selectedQuarters.forEach(qName => {
                const quarter = Quarters.find(q => q.name === qName);
                if (quarter) months.push(...quarter.months);
            });
            return Array.from(new Set(months)).sort((a, b) => a - b);
        } else if (selectedMonths.length > 0) {
            return selectedMonths.map(m => monthMap[m]).sort((a, b) => a - b);
        }
        return [];
    };

    const targetMonths = getSelectedMonthIndices();


    if (selectedYears.length === 1 && (selectedMonths.length > 1 || selectedQuarters.length > 0)) {
        // กรณี: เทียบเดือน (ในปีเดียวกัน) หรือเลือกไตรมาส
        finalCategories = categoryOrder; 
        const selectedYear = selectedYears[0];
        const monthsToDisplay = selectedQuarters.length > 0 ? targetMonths : targetMonths;

        monthsToDisplay.forEach((monthIndex) => {
            const monthlyData = categoryOrder.map((category) => {
                const dataObj = data.monthly_data[selectedYear]?.[monthIndex]?.[category];
                return getValue(dataObj);
            });
            
            dataForAverageCalc.push(monthlyData);
            finalSeries.push({
                name: `${Months[monthIndex - 1]} ${selectedYear}`,
                type: 'column',
                data: monthlyData
            });
        });
    } else if (selectedYears.length > 1 && (selectedMonths.length > 1 || selectedQuarters.length > 0)) {
        // กรณี: เทียบปีและเดือน/ไตรมาส (โค้ดเดิมของคุณจำกัดที่ 2 ซีรีส์)
        finalCategories = categoryOrder;
        const monthsToDisplay = selectedQuarters.length > 0 ? targetMonths : targetMonths;

        const firstYear = selectedYears[0];
        const secondYear = selectedYears[1];

        // Only compare the first selected month/quarter period across years for simplicity
        if (monthsToDisplay.length > 0) {
            const monthIndex = monthsToDisplay[0];
            const monthName = Months[monthIndex - 1];

            // Series 1 (First Year)
            const monthlyData1 = categoryOrder.map((category) => getValue(data.monthly_data[firstYear]?.[monthIndex]?.[category]));
            dataForAverageCalc.push(monthlyData1);
            finalSeries.push({ name: `${monthName} ${firstYear}`, type: 'column', data: monthlyData1 });

            // Series 2 (Second Year)
            if (secondYear) {
                const monthlyData2 = categoryOrder.map((category) => getValue(data.monthly_data[secondYear]?.[monthIndex]?.[category]));
                dataForAverageCalc.push(monthlyData2);
                finalSeries.push({ name: `${monthName} ${secondYear}`, type: 'column', data: monthlyData2 });
            }
        }
    } else if (selectedYears.length === 1 && selectedMonths.length === 0 && selectedQuarters.length === 0) { 
        // กรณี: สรุปรายปี 1 ปี (และไม่มีเดือน/ไตรมาส)
        finalCategories = categoryOrder;
        const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[selectedYears[0]]?.[category]));
        dataForAverageCalc.push(yearlyData);
        finalSeries.push({ name: `ปี ${selectedYears[0]}`, type: 'column', data: yearlyData });
    } else if (selectedMonths.length === 0 && selectedQuarters.length === 0 && selectedYears.length > 1) { 
        // กรณี: เทียบปี (สรุปรายปี) (และไม่มีเดือน/ไตรมาส)
        finalCategories = categoryOrder;
        selectedYears.forEach((year) => {
            const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[year]?.[category]));
            dataForAverageCalc.push(yearlyData);
            finalSeries.push({ name: `ปี ${year}`, type: 'column', data: yearlyData });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 1 && selectedQuarters.length === 0) { 
        // กรณี: สรุป 1 เดือน 1 ปี (ไม่มีไตรมาส)
        finalCategories = categoryOrder;
        const selectedYear = selectedYears[0];
        const monthIndex = monthMap[selectedMonths[0]];
        
        const monthlyData = categoryOrder.map((category) => getValue(data.monthly_data[selectedYear]?.[monthIndex]?.[category]));
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
    const selectedYears = selectyear.value;
    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value;

    const yearText = selectedYears.length === 1 ? `ปี ${selectedYears[0]}` : 
                     selectedYears.length > 1 ? `ปี ${selectedYears[0]} - ปี ${selectedYears[selectedYears.length - 1]}` : '';

    // 1. Priority: Quarters Selected (Explicit selection)
    if (selectedQuarters.length > 0) {
        const quarterNames = selectedQuarters.join(', ');
        return `ไตรมาส: ${quarterNames} ${yearText}`;
    }

    // 2. Check for Month Grouping (Check if selected months form a perfect quarter for single year)
    if (selectedMonths.length > 0 && selectedYears.length === 1) {
        const monthIndices = selectedMonths.map(m => monthMap[m]).sort((a, b) => a - b).join(',');
        
        const Q = Quarters.find(q => q.months.join(',') === monthIndices);
        
        if (Q) {
            return `${yearText} - ${Q.name} (${Q.names.join(' - ')})`; // 👈 รูปแบบที่ผู้ใช้ต้องการ
        }
    }
    
    // 3. Fallback to existing logic

    if (selectedMonths.length === 1 && selectedYears.length === 1) {
        const selectedMonth = selectedMonths[0];
        const selectedYear = selectedYears[0];
        return `เดือน ${selectedMonth} ปี ${selectedYear}`;
    } else if (selectedMonths.length > 1 && selectedYears.length === 1) {
        const months = selectedMonths.join(' - ');
        const selectedYear = selectedYears[0];
        return `เดือน ${months} ปี ${selectedYear}`;
    } else if (selectedMonths.length === 1 && selectedYears.length > 1) {
        const firstYear = selectedYears[0];
        const lastYear = selectedYears[selectedYears.length - 1];
        const selectedMonth = selectedMonths[0];
        return `เดือน ${selectedMonth} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectedMonths.length > 1 && selectedYears.length > 1) {
        const months = selectedMonths.join(' - ');
        const firstYear = selectedYears[0];
        const lastYear = selectedYears[selectedYears.length - 1];
        return `เดือน ${months} ปี ${firstYear} - ปี ${lastYear}`;
    } else if (selectedYears.length === 1 && selectedMonths.length === 0) { 
        const selectedYear = selectedYears[0];
        return `สรุปรายปี ${selectedYear}`; 
    } else if (selectedYears.length > 1 && selectedMonths.length === 0) { 
        const firstYear = selectedYears[0];
        const lastYear = selectedYears[selectedYears.length - 1];
        return `เปรียบเทียบรายปี ${firstYear} - ปี ${lastYear}`; 
    }

    // Default when 0 selection
    const { currentBuddhistYear } = getCurrentPeriod();
    const currentMonthName = Months[new Date().getMonth()];
    
    if (selectedYears.length === 1 && selectedYears[0] === currentBuddhistYear) {
         return `สรุปยอดเซ็นสัญญา ถึงเดือน ${currentMonthName} ปี ${currentBuddhistYear}`;
    }
    
    return 'กรุณาเลือกข้อมูลเพื่อแสดงผล';
});

watch(
    [selectyear, selectMonths, selectQuarters], // 👈 NEW: Watch Quarters
    () => {
        // Clear conflicting selection: If quarters selected, clear months (only update local ref, API call handles the final data array)
        if (selectQuarters.value.length > 0 && selectMonths.value.length > 0) {
            selectMonths.value = [];
        }
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
    const selectedQuarters = selectQuarters.value; // 👈 NEW: Use quarters filter
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    
    let periods: { key: string, label: string, year: string, monthIndex?: number }[] = [];

    // --- Case A: Quarters are explicitly selected ---
    if (selectedQuarters.length > 0) {
        const selectedQuarterMonths: number[] = [];
        
        selectQuarters.value.forEach(qName => {
            const quarter = Quarters.find(q => q.name === qName);
            if (quarter) {
                selectedQuarterMonths.push(...quarter.months);
            }
        });
        
        // Ensure unique months and sort them
        const uniqueMonths = Array.from(new Set(selectedQuarterMonths)).sort((a, b) => a - b);

        selectedYears.forEach(year => {
            uniqueMonths.forEach(monthIndex => {
                periods.push({
                    key: `M${monthIndex}Y${year}`,
                    label: `${Months[monthIndex - 1]} ${year}`,
                    year: year,
                    monthIndex: monthIndex
                });
            });
        });
    }
    // --- Case B: Months are explicitly selected (Original logic) ---
    else if (selectedMonths.length > 0) {
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
    // --- Case C: No Months/Quarters selected (Original default logic) ---
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
    metricKey: keyof Metrics | 'average_price_per_sqm'; // Use keyof Metrics
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
    const allCategories = [...valueCategories, 'รวม']; // Categories to display

    // Function to get Metrics object for a given period and category
    const getMetrics = (period: typeof currentPeriods[0], category: string): Metrics => {
        let metrics: Metrics | undefined;
        
        // Use monthly data if month index is available (for month-by-month and quarter views)
        if (period.monthIndex && period.monthIndex !== 0) {
            metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
        } 
        // Use yearly data if no month index is available (for year summary views)
        else if (!period.monthIndex) {
            metrics = summaryData.value?.yearly_data[period.year]?.[category];
        }
        
        // Return initialized metrics if data is missing, ensures keys exist
        if (!metrics) {
            return { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
        }
        return metrics;
    };


    const finalTable: TableCategory[] = [];

    allCategories.forEach(categoryName => {
        const categoryData: TableCategory = {
            categoryName: categoryName,
            rows: []
        };

        metricRows.forEach(metric => {
            const row: TableRow = {
                metricKey: metric.key as keyof Metrics | 'average_price_per_sqm', // Cast to correct type
                metricName: metric.name,
                format: metric.format,
                data: {}
            };
            
            let totalMetricValue = 0; // Sum of Metric Value for 'รวมทุกช่วง' (for total_value, total_area, total_units)
            let totalValueForAvg = 0; // Temp variable for calculating overall avg_price_per_sqm
            let totalAreaForAvg = 0; // Temp variable for calculating overall avg_price_per_sqm

            // 1. Process data for each data period
            currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                const periodKey = p.key;
                
                // Get the Metrics object from summaryData
                const metrics = getMetrics(p, categoryName);
                
                // Extract the specific metric value
                let metricValue: number = metrics[metric.key as keyof Metrics] || 0; 
                
                // Store metric value for this period
                row.data[periodKey] = metricValue;

                // 2. Accumulate values for 'รวมทุกช่วง' (Only for summetric, not for average)
                if (metric.key !== 'average_price_per_sqm') {
                    totalMetricValue += metricValue;
                }
                
                // 3. Accumulate total value and total area across periods for final average calculation
                totalValueForAvg += metrics.total_value;
                totalAreaForAvg += metrics.total_area;
            }); 

            // 4. Calculate and add 'รวมทุกช่วง' column
            if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                let grandTotalMetricValue: number;

                if (metric.key === 'average_price_per_sqm') {
                    // Recalculate average price for the total period
                    grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                } else {
                    // Use the simple sum for total_value, total_area, total_units
                    grandTotalMetricValue = totalMetricValue;
                }
                
                row.data[grandTotalPeriodKey] = grandTotalMetricValue;
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
                                    เปรียบเทียบรายเดือน/ไตรมาส
                                </v-tab>
                            </v-tabs>

                            <v-window v-model="tab" class="pt-5">
                                <v-window-item value="monthly">
                                    <v-container fluid class="pa-0">
                                        <v-alert density="compact" variant="tonal" color="info" class="mb-4">
                                            <b>วิธีใช้งาน:</b><br />
                                            - <b>ไม่เลือกปี/เดือน/ไตรมาส:</b> แสดงข้อมูลของปีปัจจุบันถึงเดือนปัจจุบัน<br />
                                            - <b>เทียบเดือน/ไตรมาส (ในปีเดียวกัน):</b> เลือก 1 ปี และเลือกหลายเดือน/ไตรมาส<br />
                                            - <b>เทียบปี (ในเดือน/ไตรมาสเดียวกัน):</b> เลือกหลายปี และเลือก 1 เดือน/ไตรมาส
                                        </v-alert>

                                        <v-row>
                                            <v-col cols="12" md="4">
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
                                            <v-col cols="12" md="4">
                                                <v-combobox
                                                    v-model="selectQuarters"
                                                    :items="Quarters.map(q => q.name)"
                                                    label="เลือกไตรมาส"
                                                    chips
                                                    multiple
                                                    clearable
                                                    variant="outlined"
                                                    density="comfortable"
                                                ></v-combobox>
                                            </v-col>
                                            <v-col cols="12" md="4">
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
                                                    :class="{'text-primary font-weight-bold': category.categoryName === 'รวม' && row.metricKey === 'total_value', 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key}"
                                                >
                                                    {{ (row.data[period.key] || 0) !== 0 ? row.format(row.data[period.key] || 0) : '-' }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 2" class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-app>
</template>