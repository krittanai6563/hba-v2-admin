<script setup lang="ts">
import Quarterly_Value_Report from '@/components/dashboard/Quarterly_Value_Report.vue';
import Regional_report from '@/components/dashboard/Regional_report.vue';

// -------------------
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs'; // Import useDate for finding current month

const date = useDate(); // Initialize date utilities
const tab = ref('monthly');

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
    // 🚀 FIX 2: ปรับโครงสร้าง region_data ให้เข้าถึง Year -> Month -> Region -> PriceRange -> Metrics
    region_data?: Record<string, Record<number, Record<string, Record<string, Metrics>>>>; 
    // 🚀 FIX 3: ปรับ membership_data ให้เป็น Array ของ MemberSubmission
    membership_data?: MemberSubmission[];
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

// krittanai6563/hba-v2-admin/hba-v2-admin-c1e20efc25a1e602fca98d0fb2056104c5a1113e/src/views/components/repost_hba.vue (ประมาณบรรทัดที่ 77)

// 🚀 NEW: รายการภูมิภาคที่ใช้ในการแสดงผล
const regionCategories = [
    'กรุงเทพปริมณฑล', // <-- เพิ่ม: ภูมิภาคหลักที่มีข้อมูลในฐานข้อมูล
    'ภาคกลาง', 
    'ภาคเหนือ', 
    'ภาคตะวันออกเฉียงเหนือ', 
    'ภาคใต้', 
    'ภาคตะวันออก', 
    'ภาคตะวันตก', 
    'รวมทั่วประเทศ'
];


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

// 🚀 NEW COMPUTED: Maps selected quarters to month names (for UI display)
const quartersToMonthsNames = computed<string[]>(() => {
    if (selectQuarters.value.length === 0) return [];
    
    let monthIndices: number[] = [];
    selectQuarters.value.forEach(qName => {
        const quarter = Quarters.find(q => q.name === qName);
        if (quarter) monthIndices.push(...quarter.months);
    });
    
    // Ensure unique months and sort them
    const uniqueMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
    return uniqueMonthIndices.map(index => Months[index - 1]);
});

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

    // 🚀 FIX: Prioritize Month selection for API call as it is the most granular.
    // If selectQuarters is used, the watcher already populated selectMonths.
    if (selectMonths.value.length > 0) {
        // ถ้ามีการเลือกเดือน (แม้จะมาจากไตรมาส) ให้ใช้เดือนเป็นตัวกรองหลัก
        data.months = selectMonths.value.map((month: string) => monthMap[month] || null);
        data.quarters = []; // ล้าง quarters ใน payload เพื่อป้องกันความขัดแย้งใน Backend
    } else if (selectQuarters.value.length > 0) { 
        // Fallback: ถ้าเลือกไตรมาส แต่ช่องเดือนว่าง (เป็นไปได้ถ้า watcher ไม่ทำงาน)
        data.quarters = selectQuarters.value.map((quarterName: string) => quarterMap[quarterName] || null);
        data.months = []; 
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

    // 🚀 FIX 1: Sort selected years ascending (oldest to newest)
    const sortedYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const selectedYears = sortedYears; // ใช้ array ที่เรียงแล้วสำหรับประมวลผลกราฟ

    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value; 
    const getValue = (dataObj: Metrics | undefined) => (dataObj?.total_value || 0);

    // Helper to get month indices covered by current selection (used for filtering monthlyData)
    const getSelectedMonthIndices = () => {
        if (selectedQuarters.length > 0) {
            let months: number[] = [];
            selectedQuarters.forEach(qName => {
                const quarter = Quarters.find(q => q.name === qName);
                if (quarter) months.push(...quarter.months);
            });
            // ใช้ selectMonths เป็นแหล่งข้อมูลหลัก (ถ้ามี)
            if (selectedMonths.length > 0) {
                return selectedMonths.map(m => monthMap[m]).filter(Boolean).sort((a, b) => a - b);
            }
            return Array.from(new Set(months)).sort((a, b) => a - b);
        } else if (selectedMonths.length > 0) {
            return selectedMonths.map(m => monthMap[m]).filter(Boolean).sort((a, b) => a - b);
        }
        return [];
    };

    const targetMonths = getSelectedMonthIndices();


    if (selectedYears.length === 1 && (selectedMonths.length > 1 || selectedQuarters.length > 0)) {
        // กรณี: เทียบเดือน (ในปีเดียวกัน) หรือเลือกไตรมาส
        finalCategories = categoryOrder; 
        const selectedYear = selectedYears[0];
        const monthsToDisplay = targetMonths;

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
    } else if (selectedYears.length > 1 && (selectedMonths.length > 0 || selectedQuarters.length > 0)) {
        // กรณี: เทียบปีและเดือน/ไตรมาส - แสดงทุกช่วงเวลาที่เลือกข้ามปี
        finalCategories = categoryOrder;
        const monthsToDisplay = getSelectedMonthIndices(); 

        monthsToDisplay.forEach((monthIndex) => {
            const monthName = Months[monthIndex - 1];
            
            // 🚀 FIX 2: ใช้ sortedYears ที่เรียงแล้ว
            selectedYears.forEach((year) => {
                const monthlyData = categoryOrder.map((category) => getValue(data.monthly_data[year]?.[monthIndex]?.[category]));
                
                dataForAverageCalc.push(monthlyData);
                finalSeries.push({ name: `${monthName} ${year}`, type: 'column', data: monthlyData });
            });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 0 && selectedQuarters.length === 0) { 
        // กรณี: สรุปรายปี 1 ปี (และไม่มีเดือน/ไตรมาส)
        finalCategories = categoryOrder;
        const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[selectedYears[0]]?.[category]));
        dataForAverageCalc.push(yearlyData);
        finalSeries.push({ name: `ปี ${selectedYears[0]}`, type: 'column', data: dataForAverageCalc[0] });
    } else if (selectedMonths.length === 0 && selectedQuarters.length === 0 && selectedYears.length > 1) { 
        // กรณี: เทียบปี (สรุปรายปี) (และไม่มีเดือน/ไตรมาส)
        finalCategories = categoryOrder;
        // 🚀 FIX 2: ใช้ sortedYears ที่เรียงแล้ว
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

    // 🚀 FIX 3: ลบการจำกัดจำนวนแท่งกราฟ (finalSeries.slice(0, 3))
    let finalChartSeries = finalSeries;
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
        
        finalChartSeries.push({
            name: 'ค่าเฉลี่ย',
            type: 'line',
            data: averageData,
        });
    }
    
    chartSeries.value = finalChartSeries;
};
    
   
const chartSubtitle = computed(() => {
    // 🚀 FIX 1 & 2: Get Sorted Years (Ascending)
    const sortedYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const firstYear = sortedYears[0];
    const lastYear = sortedYears[sortedYears.length - 1];

    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value;

    const yearText = sortedYears.length === 1 ? `ปี ${firstYear}` : 
                     sortedYears.length > 1 ? `ปี ${firstYear} - ปี ${lastYear}` : '';

    // 🚀 FIX 3: Get Sorted Month Indices (from Quarters or Months)
    let monthIndices: number[] = [];
    if (selectedQuarters.length > 0) {
        // ใช้เดือนที่ถูก populate ใน selectMonths สำหรับการแสดงผล (รวมถึงเดือนที่ผู้ใช้อาจเพิ่มเอง)
        monthIndices = selectedMonths.map(m => monthMap[m]).filter(Boolean);

    } else if (selectedMonths.length > 0) {
        // Map to index and sort just to be safe if user manually selected months out of order
        monthIndices = selectedMonths.map(m => monthMap[m]).filter(Boolean);
    }
    
    const sortedMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
    const firstMonthIndex = sortedMonthIndices[0];
    const lastMonthIndex = sortedMonthIndices[sortedMonthIndices.length - 1];
    const firstMonthName = firstMonthIndex ? Months[firstMonthIndex - 1] : '';
    const lastMonthName = lastMonthIndex ? Months[lastMonthIndex - 1] : '';

    // 1. Priority: Quarters Selected (Explicit selection)
    if (selectedQuarters.length > 0) {
        const quarterNames = selectedQuarters.join(', ');
        // 🚀 FIX 4: Show month range in quarter subtitle
        if (firstMonthName && lastMonthName) {
            // ใช้ชื่อไตรมาสที่เลือก + ช่วงเดือนที่ครอบคลุม (ซึ่งอาจรวมเดือนที่เพิ่มเอง)
            return `ไตรมาส: ${quarterNames} (${firstMonthName} - ${lastMonthName}) ${yearText}`;
        }
        return `ไตรมาส: ${quarterNames} ${yearText}`;
    }

    // 2. Multiple Months Selected (Range format: Month Start - Month End)
    if (sortedMonthIndices.length > 1) {
        
        // Check for single year perfect quarter match (preserve logic)
        if (sortedYears.length === 1) {
             const monthIndicesString = sortedMonthIndices.join(',');
             const Q = Quarters.find(q => q.months.join(',') === monthIndicesString);
            
             if (Q) {
                 // Format: ปี 2567 - ไตรมาส 1 (มกราคม - มีนาคม)
                 return `${yearText} - ${Q.name} (${Q.names.join(' - ')})`; 
             }
        }
        
        // 🚀 FIX 5: Fallback to Month Range (start - end)
        if (sortedYears.length === 1) {
             return `เดือน ${firstMonthName} - ${lastMonthName} ปี ${firstYear}`;
        } else if (sortedYears.length > 1) {
             // Example: เดือน มกราคม - มีนาคม ปี 2566 - ปี 2568
             return `เดือน ${firstMonthName} - ${lastMonthName} ปี ${firstYear} - ปี ${lastYear}`;
        }
    } 
    // 3. Single Month Selected 
    else if (sortedMonthIndices.length === 1) {
        const selectedMonthName = firstMonthName;
        if (sortedYears.length === 1) {
            return `เดือน ${selectedMonthName} ปี ${firstYear}`;
        } else if (sortedYears.length > 1) {
            // Example: เดือน มกราคม ปี 2566 - ปี 2568
            return `เดือน ${selectedMonthName} ปี ${firstYear} - ปี ${lastYear}`;
        }
    }
    // 4. Yearly Summary only (No Months/Quarters)
    else if (sortedYears.length > 0) {
        if (sortedYears.length === 1) {
            return `สรุปรายปี ${firstYear}`;
        } else { 
            return `เปรียบเทียบรายปี ${firstYear} - ปี ${lastYear}`; 
        }
    }

    // 5. Default/No Selection
    const { currentBuddhistYear } = getCurrentPeriod();
    const currentMonthName = Months[new Date().getMonth()];
    
    // Fallback if no year selected but default to current year is active
    if (sortedYears.length === 1 && sortedYears[0] === currentBuddhistYear) {
         return `สรุปยอดเซ็นสัญญา ถึงเดือน ${currentMonthName} ปี ${currentBuddhistYear}`;
    }
    
    return 'กรุณาเลือกข้อมูลเพื่อแสดงผล';
});


// 🚀 NEW WATCHER 1: Auto-select months based on quarters (for UI display)
watch(selectQuarters, (newQuarters) => {
    if (newQuarters.length > 0) {
        // เมื่อเลือกไตรมาส ให้เลือกเดือนที่อยู่ในไตรมาสนั้นอัตโนมัติ (สำหรับแสดงผลใน UI)
        selectMonths.value = quartersToMonthsNames.value;
    } 
}, { immediate: false }); 

watch(
    [selectyear, selectMonths, selectQuarters], 
    () => {
        // ลบเงื่อนไขเก่าที่เคยล้าง selectMonths ออกไป
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
    // 🚀 FIX: ปิดตัวเลขบนแกน Y
    yaxis: {
        labels: {
            show: false
        }
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
    categoryName: string; // Used for Price Range in price-based table, or Region in region-based table
    rows: TableRow[];
}

// 🚀 Helper Function: ดึงข้อมูล Metrics ตามช่วงเวลาและภูมิภาค (ปรับให้รับ category ด้วย)
// krittanai6563/hba-v2-admin/hba-v2-admin-c1e20efc25a1e602fca98d0fb2056104c5a1113e/src/views/components/repost_hba.vue (ประมาณบรรทัดที่ 319)

// 🚀 Helper Function: ดึงข้อมูล Metrics ตามช่วงเวลาและภูมิภาค (ปรับให้รับ category ด้วย)
const getRegionalMetrics = (period: typeof tablePeriods.value[0], region: string, category: string): Metrics => {
    let metrics: Metrics | undefined;
    
    // 1. Fallback: Aggregate National Totals ('รวมทั่วประเทศ') จาก monthly/yearly data ปกติ
    if (region === 'รวมทั่วประเทศ') {
         if (period.monthIndex && period.monthIndex !== 0) {
             // ใช้ monthly_data สำหรับเดือน-เดือน (Monthly total)
             metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
         } else if (!period.monthIndex && period.year !== 'TOTAL') {
             // ใช้ yearly_data สำหรับสรุปปี (Yearly total)
             metrics = summaryData.value?.yearly_data[period.year]?.[category];
         }
    }
    
    // 2. ดึงข้อมูลจาก region_data
    if (!metrics && region !== 'รวมทั่วประเทศ') {
         // regionDataForYear เป็น Record<number, Record<string, Record<string, Metrics>>>
         const regionDataForYear = summaryData.value?.region_data?.[period.year];
         
         if (period.monthIndex && period.monthIndex !== 0) {
             // กรณี: ข้อมูลรายเดือน/ไตรมาส (Lookup by specific month)
             metrics = regionDataForYear?.[period.monthIndex]?.[region]?.[category]; 
         } 
         
         // 🚀 FIX: กรณี: ข้อมูลสรุปรายปี (ไม่มี monthIndex) -> ต้องรวมยอดรายเดือนทั้งหมดใน Vue
         else if (!period.monthIndex && period.year !== 'TOTAL' && regionDataForYear) { 
             
             // 🚀 FIX Error 2: Initialize with the full Metrics type
             let annualMetrics: Metrics = { 
                 total_value: 0, 
                 total_area: 0, 
                 total_units: 0,
                 average_price_per_sqm: 0 // กำหนดค่าเริ่มต้นแล้ว
             };
             let foundData = false;
             
             // 🚀 FIX Error 1: Use Object.keys() เพื่อวนซ้ำ Key (เป็น string) แล้วใช้ parseInt แปลงกลับเป็น number
             const monthKeys = Object.keys(regionDataForYear); 
             
             for (const monthKey of monthKeys) { 
                 const monthIndexAsNumber = parseInt(monthKey); // แปลง Key เป็น Number
                 
                 const monthlyMetrics = regionDataForYear[monthIndexAsNumber]?.[region]?.[category];

                 if (monthlyMetrics) {
                     annualMetrics.total_value += monthlyMetrics.total_value;
                     annualMetrics.total_area += monthlyMetrics.total_area;
                     annualMetrics.total_units += monthlyMetrics.total_units;
                     foundData = true;
                 }
             }

             if (foundData) {
                 // 🚀 FIX Error 2: คำนวณค่าเฉลี่ยและเก็บใน property ที่นิยามไว้แล้ว
                 annualMetrics.average_price_per_sqm = (annualMetrics.total_area > 0) ? (annualMetrics.total_value / annualMetrics.total_area) : 0;
                 metrics = annualMetrics;
             }
         }
    }
    
    // Return initialized metrics if data is missing
    if (!metrics) {
        return { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
    }
    return metrics;
};
// 🚀 LOGIC ใหม่: Computed Property สำหรับกำหนดช่วงเวลาแสดงผลในตาราง (Column Headers)
const tablePeriods = computed(() => {
    const selectedYears = selectyear.value;
    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value; // 👈 NEW: Use quarters filter
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    
    let periods: { key: string, label: string, year: string, monthIndex?: number }[] = [];

    // 🚀 FIX 4: Sort selected years ascending (oldest to newest)
    const sortedYears = [...selectedYears].sort((a, b) => a.localeCompare(b, 'th-TH'));

    // --- Case A: Quarters are explicitly selected ---
    if (selectedQuarters.length > 0) {
        // ใช้ months ที่ถูก populate ใน selectMonths เป็นรายการเดือนที่ต้องการแสดง
        const selectedMonthNames = selectMonths.value;
        const uniqueMonthIndices = selectedMonthNames.map(m => monthMap[m]).filter(Boolean).sort((a, b) => a - b);
        
        sortedYears.forEach(year => {
            uniqueMonthIndices.forEach(monthIndex => {
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
    // krittanai6563/hba-v2-admin/hba-v2-admin-c1e20efc25a1e602fca98d0fb2056104c5a1113e/src/views/components/repost_hba.vue (ประมาณบรรทัดที่ 499)

// ...

    // --- Case B: Months are explicitly selected (Original logic) ---
    // krittanai6563/hba-v2-admin/hba-v2-admin-c1e20efc25a1e602fca98d0fb2056104c5a1113e/src/views/components/repost_hba.vue

// ... (ประมาณบรรทัดที่ 499)

    // --- Case B: Months are explicitly selected (Original logic) ---
    else if (selectedMonths.length > 0) {
        // 💡 Note: selectedMonths are already sorted when coming from quartersToMonthsNames
        // หรือมีการเลือกเอง
        
        // 1. Map ชื่อเดือนที่เลือกเป็น Index (Number) และกรองชื่อที่ไม่ถูกต้องออก
        const monthIndices = selectedMonths.map(m => monthMap[m]).filter(Boolean) as number[];
        // 2. ลบ Index ซ้ำซ้อน และเรียงลำดับ
        const sortedUniqueMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
        
        sortedYears.forEach(year => {
            sortedUniqueMonthIndices.forEach(monthIndex => {
                const monthName = Months[monthIndex - 1]; // ดึงชื่อเดือนที่ถูกต้องจาก Index
                periods.push({
                    key: `M${monthIndex}Y${year}`,
                    label: `${monthName} ${year}`,
                    year: year,
                    monthIndex: monthIndex
                });
            });
        });
    } 
// ...
// ...
    // --- Case C: No Months/Quarters selected (Original default logic) ---
    else {
        // B1: Single Year selected (current year) AND no month -> Default to Jan - Current Month of current year
        if (sortedYears.length === 1 && sortedYears[0] === currentBuddhistYear) {
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
        else if (sortedYears.length > 0) {
            sortedYears.forEach(year => {
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

    // 🚀 FIX 5: Sort periods chronologically (Year Ascending, then Month Index Ascending)
    periods.sort((a, b) => {
        if (a.year !== b.year) {
            return a.year.localeCompare(b.year, 'th-TH'); // Sort by year ascending
        }
        // If years are the same, sort by month index (if available, non-monthly (yearly summary) comes first)
        const monthA = a.monthIndex || 0;
        const monthB = b.monthIndex || 0;
        return monthA - monthB; // Sort by month index ascending
    });
    

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


// 🚀 LOGIC เดิม: Computed Property สำหรับตารางสรุปมูลค่าบ้าน (Price Range)
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

// 🚀 LOGIC เดิม: Computed Property สำหรับตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค (Region only)
const regionReportTableData = computed<TableCategory[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';

    const finalTable: TableCategory[] = [];

    // 🚀 ใช้ regionCategories เป็นแถวหลัก
    regionCategories.forEach(regionName => {
        const categoryData: TableCategory = {
            categoryName: regionName,
            rows: [] // rows are still metrics (total_units, total_value, etc.)
        };

        metricRows.forEach(metric => {
            const row: TableRow = {
                metricKey: metric.key as keyof Metrics | 'average_price_per_sqm',
                metricName: metric.name,
                format: metric.format,
                data: {}
            };
            
            let totalMetricValue = 0;
            let totalValueForAvg = 0;
            let totalAreaForAvg = 0;

            // 1. Process data for each data period
            currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                const periodKey = p.key;
                
                // Get the Metrics object from regional data (uses 'รวม' category for region only table)
                const metrics = getRegionalMetrics(p, regionName, 'รวม'); 
                
                let metricValue: number = metrics[metric.key as keyof Metrics] || 0; 
                
                row.data[periodKey] = metricValue;

                // 2. Accumulate values for 'รวมทุกช่วง'
                if (metric.key !== 'average_price_per_sqm') {
                    totalMetricValue += metricValue;
                }
                totalValueForAvg += metrics.total_value;
                totalAreaForAvg += metrics.total_area;
            }); 

            // 3. Calculate and add 'รวมทุกช่วง' column
            if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                let grandTotalMetricValue: number;

                if (metric.key === 'average_price_per_sqm') {
                    grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                } else {
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

// 🚀 NEW INTERFACE: สำหรับตารางที่รวม ภูมิภาค x มูลค่าบ้าน
interface RegionCategoryGroup {
    regionName: string;
    categories: TableCategory[]; // TableCategory structure: categoryName (Price Range) -> rows (Metrics)
}

// 🚀 LOGIC ใหม่: Computed Property สำหรับตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค x มูลค่าบ้าน
const regionAndCategoryReportTableData = computed<RegionCategoryGroup[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';
    // Categories to display, INCLUDING 'รวม' สำหรับสรุปมูลค่าบ้านในระดับภูมิภาค
    const allPriceCategories = [...valueCategories, 'รวม']; 

    const finalTable: RegionCategoryGroup[] = [];

    // Loop 1: Group by Region
    regionCategories.forEach(regionName => {
        const regionGroup: RegionCategoryGroup = {
            regionName: regionName,
            categories: []
        };

        // Loop 2: Group by Price Range Category (within the region)
        allPriceCategories.forEach(categoryName => {
            const categoryData: TableCategory = {
                categoryName: categoryName,
                rows: []
            };

            // Loop 3: Metrics Rows
            metricRows.forEach(metric => {
                const row: TableRow = {
                    metricKey: metric.key as keyof Metrics | 'average_price_per_sqm',
                    metricName: metric.name,
                    format: metric.format,
                    data: {}
                };
                
                let totalMetricValue = 0;
                let totalValueForAvg = 0;
                let totalAreaForAvg = 0;

                // Loop 4: Periods (Columns)
                currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                    const periodKey = p.key;
                    
                    // Fetch metrics for specific Region and specific Category
                    const metrics = getRegionalMetrics(p, regionName, categoryName); 
                    
                    let metricValue: number = metrics[metric.key as keyof Metrics] || 0; 
                    
                    row.data[periodKey] = metricValue;

                    // Accumulate totals for the Grand Total Period column
                    if (metric.key !== 'average_price_per_sqm') {
                        totalMetricValue += metricValue;
                    }
                    totalValueForAvg += metrics.total_value;
                    totalAreaForAvg += metrics.total_area;
                }); 

                // 5. Calculate and add 'รวมทุกช่วง' column
                if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                    let grandTotalMetricValue: number;

                    if (metric.key === 'average_price_per_sqm') {
                        grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                    } else {
                        grandTotalMetricValue = totalMetricValue;
                    }
                    
                    row.data[grandTotalPeriodKey] = grandTotalMetricValue;
                }

                categoryData.rows.push(row);
            });
            regionGroup.categories.push(categoryData);
        });
        finalTable.push(regionGroup);
    });
    
    return finalTable;
});


// 🚀 NEW LOGIC: Computed Property สำหรับ Polar Chart Data (Price Range)
const polarChartPriceData = computed(() => {
    const data = monthlyReportTableData.value;
    if (!data || data.length === 0) {
        return { series: [], labels: [] };
    }

    const priceLabels = valueCategories; // Include categories excluding 'รวม'
    const seriesData: number[] = [];

    priceLabels.forEach(categoryName => {
        // Find the specific category group in the table data
        const categoryGroup = data.find(c => c.categoryName === categoryName);
        if (categoryGroup) {
            // Find the total_value row
            const totalValueRow = categoryGroup.rows.find(r => r.metricKey === 'total_value');
            
            // Get the aggregated total value for all periods ('TOTAL_PERIODS')
            const totalValue = totalValueRow?.data['TOTAL_PERIODS'] || 0;
            // 🚀 FIX: Scale down the series data to millions for better chart rendering
            seriesData.push(totalValue / 1000000); 
        } else {
             seriesData.push(0);
        }
    });

    return {
        series: seriesData,
        labels: priceLabels,
        totalValueSum: seriesData.reduce((sum, value) => sum + value, 0)
    };
});

// 🚀 NEW LOGIC: Computed Property สำหรับ Polar Chart Data (Region)
const polarChartRegionData = computed(() => {
    const data = regionReportTableData.value;
    if (!data || data.length === 0) {
        return { series: [], labels: [] };
    }

    // Exclude 'รวมทั่วประเทศ' for visualization slices
    const regionLabels = regionCategories.filter(reg => reg !== 'รวมทั่วประเทศ'); 
    const seriesData: number[] = [];

    regionLabels.forEach(regionName => {
        const regionGroup = data.find(c => c.categoryName === regionName);
        if (regionGroup) {
            const totalValueRow = regionGroup.rows.find(r => r.metricKey === 'total_value');
            const totalValue = totalValueRow?.data['TOTAL_PERIODS'] || 0;
            // 🚀 FIX: Scale down the series data to millions for better chart rendering
            seriesData.push(totalValue / 1000000); 
        } else {
             seriesData.push(0);
        }
    });

    return {
        series: seriesData,
        labels: regionLabels,
        totalValueSum: seriesData.reduce((sum, value) => sum + value, 0)
    };
});

// Define new Chart Options specifically for Polar Area Charts (reusing formatting)
const polarChartOptions = computed(() => ({
    chart: {
        type: 'polarArea',
        height: 400,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
    },
    // labels: handled dynamically in the template using labels from computed data
    stroke: {
        colors: ['#fff']
    },
    fill: {
        opacity: 0.9
    },
    legend: {
        position: 'bottom',
        offsetY: 0
    },
    
    // 🚀 FIX: Add dataLabels configuration
    dataLabels: {
        enabled: true,
        formatter: function(val: number, opts: any) {
            // Calculate the total sum of the current series data
            const totalSum = opts.w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0);
            
            // Calculate the percentage
            const percentage = totalSum > 0 ? ((val / totalSum) * 100).toFixed(1) : '0.0';
            
            // 🚀 FIX: กู้คืนค่าจริง โดยคูณกลับด้วย 1,000,000 (เนื่องจากข้อมูลถูก Scale ใน computed property)
            const actualValue = val * 1000000; 
            
            // Format the value (จำนวนเต็ม)
            const formattedValue = actualValue.toLocaleString('th-TH', { maximumFractionDigits: 0 });
            
            // Return an array of strings to display value and percentage stacked
            return [
                formattedValue + ' บาท', // แสดงค่าจำนวนเต็ม
                percentage + '%'        // แสดงค่าเปอร์เซ็นต์
            ];
        },
        style: {
            fontSize: '12px',
            fontWeight: 'bold',
        },
        dropShadow: {
            enabled: true,
            top: 1,
            left: 1,
            blur: 1,
            opacity: 0.5
        }
    },

    tooltip: {
        y: {
            formatter: (val: number) => {
                // Reuse metric formatting logic for tooltip
                // Tooltip needs the raw value, so we scale it back up
                const actualValue = val * 1000000;
                return actualValue.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' บาท';
            }
        }
    },
    plotOptions: {
        polarArea: {
            rings: {
                strokeWidth: 0
            },
            spokes: {
                strokeWidth: 0
            },
        }
    }
}));


interface MemberSubmission {
    member_id: string;
    name: string;
    // 🚀 FIX 1: เพิ่ม 'master' เพื่อรองรับบทบาทผู้ดูแลระบบสูงสุด
    role: 'user' | 'admin' | 'master'; 
    total_submitted_count: number; // All time total
    submissions_by_year: Record<string, number>; // e.g., {'2568': 10, '2567': 5}
    submissions_in_period: Record<string, number[]>; // key=year, value=array of submitted month indices (1-12)
}

// ⚠️ MOCK FUNCTION: REPLACE THIS WITH REAL API PARSING OF summaryData.value.membership_data
const generateMockMemberData = (): MemberSubmission[] => {
    const memberData = summaryData.value?.membership_data || [];

    // ตรวจสอบและคืนค่าเป็น Array ของ MemberSubmission
    if (Array.isArray(memberData)) {
        return memberData as MemberSubmission[];
    }
    
    console.warn('Membership data format is incorrect or empty:', memberData);
    return [];
};

// **หมายเหตุ:** คุณอาจต้องเปลี่ยนชื่อฟังก์ชันนี้ในโค้ด Vue จาก 'generateMockMemberData'
// เป็น 'getMemberData' และอัปเดตการเรียกใน computed properties 'memberSubmissionSummary', 'memberListChartData', และ 'memberMonthlySubmissionTableData' ด้วย
// เพื่อให้สอดคล้องกับการเปลี่ยนเป็นข้อมูลจริง

// 🚀 NEW LOGIC: Summary Data for Table/Donut Chart
const memberSubmissionSummary = computed(() => {
    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');
    const targetYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const yearA = targetYears[0];
    const yearB = targetYears[1];

    const allUsersCount = users.length;
    let submittedTotal = 0;
    let submittedYearA = 0;
    let submittedYearB = 0;
    let notSubmittedTotal = 0;
    
    users.forEach(user => {
        const hasSubmitted = user.total_submitted_count > 0;
        
        if (hasSubmitted) {
            submittedTotal++;
        } else {
            notSubmittedTotal++;
        }
        
        if (yearA && user.submissions_by_year[yearA] && user.submissions_by_year[yearA] > 0) {
            submittedYearA++;
        }
        if (yearB && user.submissions_by_year[yearB] && user.submissions_by_year[yearB] > 0) {
            submittedYearB++;
        }
    });

    return {
        // For Table
        totalUsers: allUsersCount,
        submittedTotal: submittedTotal,
        submittedYearA: submittedYearA,
        submittedYearB: submittedYearB,
        notSubmittedTotal: notSubmittedTotal,
        yearA: yearA,
        yearB: yearB,
        // For Donut Chart (Total Submitted vs Not Submitted)
        donutData: [submittedTotal, notSubmittedTotal],
        donutLabels: ['เคยส่งสัญญาแล้ว', 'ไม่เคยส่งสัญญา'],
        totalUsersIncludingAdmin: allMembers.length // For Total Members table row
    };
});

// 🚀 NEW LOGIC: Data for Horizontal Bar Chart (Submissions by Member Name)
const memberListChartData = computed(() => {
    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');
    const targetYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));

    // Aggregate submissions for all selected years
    const aggregatedUsers = users.map(user => {
        let totalSubmissionsInPeriod = 0;
        targetYears.forEach(year => {
            totalSubmissionsInPeriod += (user.submissions_by_year[year] || 0);
        });
        return {
            name: user.name,
            submissions: totalSubmissionsInPeriod,
        };
    }).filter(u => u.submissions > 0); // Only show members who submitted > 0 in the selected period

    // Sort by submissions descending
    aggregatedUsers.sort((a, b) => b.submissions - a.submissions);
    
    return {
        series: [{
            name: `จำนวนสัญญาที่กรอก (ปีที่เลือก)`,
            data: aggregatedUsers.map(u => u.submissions)
        }],
        categories: aggregatedUsers.map(u => u.name)
    };
});


// 🚀 NEW CHART OPTIONS: Horizontal Bar Chart
const barChartOptions = computed(() => ({
    chart: {
        type: 'bar',
       height: memberListChartData.value.categories.length > 0 
                ? 350 + (memberListChartData.value.categories.length * 30) 
                : 350,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: false },
    },
    plotOptions: {
        bar: {
            horizontal: true,
            dataLabels: {
                position: 'top',
            },
            borderRadius: 4
        }
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 }),
        offsetX: 0
    },
    xaxis: {
        categories: memberListChartData.value.categories,
        title: {
            text: 'จำนวนสัญญา'
        }
    },
    tooltip: {
        y: {
            formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 })
        }
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
    legend: { show: false }
}));

// 🚀 NEW CHART OPTIONS: Donut Chart (based on polar options)
const donutChartOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
    },
    labels: memberSubmissionSummary.value.donutLabels,
    legend: {
        position: 'bottom',
        offsetY: 0
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number, opts: any) => {
            const sum = memberSubmissionSummary.value.donutData.reduce((a, b) => a + b, 0);
            const absoluteValue = opts.w.globals.series[opts.seriesIndex];
            const percentage = (absoluteValue / sum) * 100;
            return `${absoluteValue} (${percentage.toFixed(1)}%)`;
        }
    },
    tooltip: {
        y: {
            formatter: (val: number, opts: any) => {
                 const sum = memberSubmissionSummary.value.donutData.reduce((a, b) => a + b, 0);
                 const percentage = (val / sum) * 100;
                 return `${val.toLocaleString('th-TH', { maximumFractionDigits: 0 })} (${percentage.toFixed(1)}%)`;
            }
        }
    },
    responsive: [{
        breakpoint: 480,
        options: {
            chart: { width: 200 },
            legend: { position: 'bottom' }
        }
    }]
}));

// 🚀 NEW INTERFACE: สำหรับตารางแสดงสถานะการกรอกรายเดือน
interface MemberMonthlyData {
    name: string;
    submissions: Record<string, string>; // Key is periodKey (M1Y2568), Value is 'X' (submitted) or '-' (not submitted)
}

// 🚀 NEW LOGIC: Data for the Monthly Submission Table
const memberMonthlySubmissionTableData = computed<MemberMonthlyData[]>(() => {
    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');
    // กรองเอาเฉพาะคอลัมน์ที่เป็นรายเดือน/รายปี (ไม่รวมคอลัมน์ 'รวมทุกช่วง')
    const periods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS' && (p.monthIndex || p.year));

    if (periods.length === 0) return [];
    
    const tableData: MemberMonthlyData[] = [];

    users.forEach(user => {
        const row: MemberMonthlyData = {
            name: user.name,
            submissions: {}
        };
        
        periods.forEach(period => {
            const periodKey = period.key;
            
            let submittedInPeriod = false;

            if (period.monthIndex) {
                // Case: Monthly Period (M1Y2568)
                const submittedMonths = user.submissions_in_period[period.year] || [];
                submittedInPeriod = submittedMonths.includes(period.monthIndex);
            } else if (period.year) {
                // Case: Yearly Summary Period (Y2568)
                submittedInPeriod = user.submissions_by_year[period.year] > 0;
            }

            row.submissions[periodKey] = submittedInPeriod ? 'X' : '-';
        });

        tableData.push(row);
    });

    return tableData;
});


// 🚀 LOGIC เดิม: Computed Property สำหรับตารางสรุปมูลค่าบ้าน (Price Range)


// 🚀 LOGIC เดิม: Computed Property สำหรับตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค (Region only)


// 🚀 NEW INTERFACE: สำหรับตารางที่รวม ภูมิภาค x มูลค่าบ้าน
interface RegionCategoryGroup {
    regionName: string;
    categories: TableCategory[]; // TableCategory structure: categoryName (Price Range) -> rows (Metrics)
}

interface MemberSubmission {
    member_id: string;
    name: string;
    // 🚀 FIX: เพิ่ม 'master' เพื่อรองรับบทบาทผู้ดูแลระบบสูงสุด
    role: 'user' | 'admin' | 'master'; 
    total_submitted_count: number; 
    submissions_by_year: Record<string, number>;
    submissions_in_period: Record<string, number[]>;
}


// ... (existing interfaces: Metrics, SummaryData) ...

// --- NEW: Interface สำหรับตารางอัตราการเติบโต ---
interface GrowthRateMetrics {
    MoM: number | null; // Month-over-Month %
    YoY: number | null; // Year-over-Year %
    QoQ: number | null; // Quarter-over-Quarter %
    YTD: number | null; // Year-to-Date %
}

interface GrowthRatePeriods {
    [key: string]: GrowthRateMetrics; // Key คือ periodKey เช่น M1Y2568
}

interface GrowthRateCategory {
    categoryName: string; // ช่วงมูลค่าบ้าน (เช่น 'ไม่เกิน 2.50 ล้านบาท' หรือ 'รวม')
    total_value: GrowthRatePeriods; // Fixed key for total_value metrics
    total_units: GrowthRatePeriods; // Fixed key for total_units metrics
}

// Helper Type เพื่อใช้ใน Logic
type MetricGrowthKey = 'total_value' | 'total_units';

// 🚀 NEW HELPER: ฟังก์ชันสำหรับรวมยอดสะสมในช่วงเดือนที่กำหนด
const getAggregatedValue = (year: string, startMonth: number, endMonth: number, category: string, metricKey: keyof Metrics): number => {
    let sum = 0;
    const monthlyData = summaryData.value?.monthly_data[year];
    
    // YTD/QoQ ควรใช้กับ metrics ที่รวมค่าได้ (total_value, total_units, total_area)
    if (metricKey === 'average_price_per_sqm') return 0;

    if (!monthlyData) return 0;

    for (let month = startMonth; month <= endMonth; month++) {
        if (month < 1 || month > 12) continue; // Safety check
        
        const metrics = monthlyData[month]?.[category];
        if (metrics) {
            sum += metrics[metricKey] || 0;
        }
    }
    return sum;
};
// Helper function to get metric value safely
const getMetricValue = (period: typeof tablePeriods.value[0], category: string, metricKey: keyof Metrics): number => {
    let metrics: Metrics | undefined;
    
    if (period.monthIndex && period.monthIndex !== 0) {
        metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
    } else if (!period.monthIndex && period.year !== 'TOTAL') {
        metrics = summaryData.value?.yearly_data[period.year]?.[category];
    }
    
    return metrics ? (metrics[metricKey as keyof Metrics] || 0) : 0;
};

// 🚀 NEW LOGIC: Computed Property สำหรับตารางอัตราการเติบโต (MoM%, YoY%, ฯลฯ)
const growthRateReportTableData = computed<GrowthRateCategory[]>(() => {
    if (!summaryData.value || tablePeriods.value.length === 0) {
        return [];
    }

    // กรองเอาเฉพาะช่วงเวลาที่มีข้อมูล (ไม่รวม 'รวมทุกช่วง')
    const periodsToCalculate = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const allCategories = [...valueCategories, 'รวม']; // Categories to display
    const finalTable: GrowthRateCategory[] = [];
    const metricsToTrack: MetricGrowthKey[] = ['total_value', 'total_units'];

    allCategories.forEach(categoryName => {
        const categoryData: GrowthRateCategory = {
            categoryName: categoryName,
            total_value: {},
            total_units: {},
        };

        metricsToTrack.forEach(metricKey => {
            periodsToCalculate.forEach((currentPeriod, index) => {
                const currentValue = getMetricValue(currentPeriod, categoryName, metricKey as keyof Metrics);
                const periodKey = currentPeriod.key;

                let MoM: number | null = null;
                let YoY: number | null = null;
                let QoQ: number | null = null;
                let YTD: number | null = null; 
                
                // --- Logic สำหรับ YoY (ถ้าเป็น Yearly Summary) ---
                if (!currentPeriod.monthIndex && currentPeriod.year !== 'TOTAL') { 
                    const prevYear = (parseInt(currentPeriod.year) - 1).toString();
                    const prevPeriod: typeof periodsToCalculate[0] = { key: `Y${prevYear}`, label: `สรุปปี ${prevYear}`, year: prevYear };
                    const prevValue = getMetricValue(prevPeriod, categoryName, metricKey as keyof Metrics);

                    if (prevValue > 0) {
                        YoY = ((currentValue / prevValue) - 1) * 100;
                    }
                }

                if (currentPeriod.monthIndex) {
                    const currentYear = currentPeriod.year;
                    const currentMonth = currentPeriod.monthIndex;
                    
                    // 1. MoM (Month-over-Month)
                    if (index > 0) {
                        const prevPeriod = periodsToCalculate[index - 1];
                        const isPreviousMonth = (prevPeriod.monthIndex === currentMonth - 1) && (prevPeriod.year === currentYear);
                        const isJanFromDec = (currentMonth === 1) && (prevPeriod.monthIndex === 12) && (parseInt(prevPeriod.year) === parseInt(currentYear) - 1);
                        
                        if (isPreviousMonth || isJanFromDec) {
                            const prevMonthValue = getMetricValue(prevPeriod, categoryName, metricKey as keyof Metrics);
                            if (prevMonthValue > 0) {
                                MoM = ((currentValue / prevMonthValue) - 1) * 100;
                            }
                        }
                    }

                    // 2. YoY (Year-over-Year)
                    const prevYear = (parseInt(currentYear) - 1).toString();
                    const prevYearPeriod: typeof periodsToCalculate[0] = { 
                        key: `M${currentMonth}Y${prevYear}`, label: `${Months[currentMonth - 1]} ${prevYear}`,
                        year: prevYear, monthIndex: currentMonth
                    };
                    const prevYearValue = getMetricValue(prevYearPeriod, categoryName, metricKey as keyof Metrics);

                    if (prevYearValue > 0) {
                        YoY = ((currentValue / prevYearValue) - 1) * 100;
                    }
                    
                    // 3. YTD (Year-to-Date) - คำนวณเมื่อมีข้อมูลสะสม
                    const currentYTDValue = getAggregatedValue(currentYear, 1, currentMonth, categoryName, metricKey as keyof Metrics);
                    const prevYTDValue = getAggregatedValue(prevYear, 1, currentMonth, categoryName, metricKey as keyof Metrics);

                    if (prevYTDValue > 0) {
                        YTD = ((currentYTDValue / prevYTDValue) - 1) * 100;
                    }
                    
                    // 4. QoQ (Quarter-over-Quarter) - คำนวณเฉพาะเดือนสุดท้ายของไตรมาส
                    const currentQuarter = Quarters.find(q => q.months.includes(currentMonth));
                    
                    if (currentQuarter && currentQuarter.months[currentQuarter.months.length - 1] === currentMonth) {
                        const currentQuarterIndex = currentQuarter.index;
                        
                        let prevQYear = currentYear;
                        let prevQIndex: number;
                        
                        if (currentQuarterIndex > 1) {
                            prevQIndex = currentQuarterIndex - 1; // Q2 vs Q1 (Same Year)
                        } else {
                            prevQIndex = 4; // Q1 vs Q4 (Previous Year)
                            prevQYear = prevYear;
                        }
                        
                        const prevQuarter = Quarters.find(q => q.index === prevQIndex);
                        
                        if (prevQuarter) {
                            // Sum value for the current quarter
                            const currentQValue = getAggregatedValue(currentYear, currentQuarter.months[0], currentQuarter.months[currentQuarter.months.length - 1], categoryName, metricKey as keyof Metrics);
                            
                            // Sum value for the previous quarter
                            const prevQValue = getAggregatedValue(prevQYear, prevQuarter.months[0], prevQuarter.months[prevQuarter.months.length - 1], categoryName, metricKey as keyof Metrics);
                            
                            if (prevQValue > 0) {
                                QoQ = ((currentQValue / prevQValue) - 1) * 100;
                            }
                        }
                    }
                }

                // บันทึกผลลัพธ์
                categoryData[metricKey][periodKey] = {
                    MoM,
                    YoY,
                    QoQ, 
                    YTD 
                };
            });
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
            <h3 class="card-title mb-1">ตารางอัตราการเติบโต MoM% / YoY% / QoQ% / YTD% (แยกตามมูลค่าบ้าน)</h3>
            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
            
            <v-table density="compact" class="mt-4 border">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 150px;">ช่วงมูลค่าบ้าน</th>
                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 150px;">รายละเอียด</th>
                        
                        <th 
                            v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')" 
                            :key="period.key" 
                            :colspan="4" 
                            class="text-center text-h6 border-b-sm"
                            :class="{'bg-blue-grey-lighten-5': !period.monthIndex}"
                        >
                            {{ period.label }}
                        </th>
                    </tr>
                    <tr>
                         <template v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')" :key="period.key">
                            <th class="text-center text-subtitle-1" style="min-width: 80px;">MoM%</th>
                            <th class="text-center text-subtitle-1" style="min-width: 80px;">YoY%</th>
                            <th class="text-center text-subtitle-1" style="min-width: 80px;">QoQ%</th>
                            <th class="text-center text-subtitle-1 border-e" style="min-width: 80px;">YTD%</th>
                         </template>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="growthRateReportTableData.length > 0">
                        <template v-for="(category, catIndex) in growthRateReportTableData" :key="category.categoryName">
                            
                            <template v-for="(metricEntry, rowIndex) in [
                                { key: 'total_units', name: 'จำนวนหลัง', data: category.total_units },
                                { key: 'total_value', name: 'มูลค่ารวม (บาท)', data: category.total_value }
                            ]" :key="`${category.categoryName}-${metricEntry.key}`">
                                
                                <tr 
                                    :class="{ 
                                        'bg-blue-grey-lighten-5': category.categoryName === 'รวม',
                                        'border-t': rowIndex === 0,
                                    }"
                                >
                                    <td v-if="rowIndex === 0" 
                                        :rowspan="2"
                                        class="text-left font-weight-bold text-subtitle-2 border-e"
                                        :class="{'text-primary': category.categoryName === 'รวม'}"
                                    >
                                        {{ category.categoryName }}
                                    </td>
                                    
                                    <td class="text-left text-caption border-e">
                                        {{ metricEntry.name }}
                                    </td>
                                    
                                    <template v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')" :key="period.key">
                                        
                                        <td 
                                            class="text-right text-subtitle-2"
                                            :class="{'text-success': (metricEntry.data[period.key]?.MoM || 0) > 0, 'text-error': (metricEntry.data[period.key]?.MoM || 0) < 0}"
                                        >
                                            {{ metricEntry.data[period.key]?.MoM != null 
                                               ? metricEntry.data[period.key]!.MoM!.toFixed(2) + '%' 
                                               : '-' 
                                            }}
                                        </td>

                                        <td 
                                            class="text-right text-subtitle-2"
                                            :class="{'text-success': (metricEntry.data[period.key]?.YoY || 0) > 0, 'text-error': (metricEntry.data[period.key]?.YoY || 0) < 0}"
                                        >
                                            {{ metricEntry.data[period.key]?.YoY != null 
                                               ? metricEntry.data[period.key]!.YoY!.toFixed(2) + '%' 
                                               : '-' 
                                            }}
                                        </td>
                                        
                                        <td 
                                            class="text-right text-subtitle-2"
                                            :class="{'text-success': (metricEntry.data[period.key]?.QoQ || 0) > 0, 'text-error': (metricEntry.data[period.key]?.QoQ || 0) < 0}"
                                        >
                                            {{ metricEntry.data[period.key]?.QoQ != null 
                                               ? metricEntry.data[period.key]!.QoQ!.toFixed(2) + '%' 
                                               : '-' 
                                            }}
                                        </td>

                                        <td 
                                            class="text-right text-subtitle-2 border-e"
                                            :class="{'text-success': (metricEntry.data[period.key]?.YTD || 0) > 0, 'text-error': (metricEntry.data[period.key]?.YTD || 0) < 0}"
                                        >
                                            {{ metricEntry.data[period.key]?.YTD != null 
                                               ? metricEntry.data[period.key]!.YTD!.toFixed(2) + '%' 
                                               : '-' 
                                            }}
                                        </td>

                                    </template>
                                </tr>
                            </template>
                        </template>
                    </template>
                    <tr v-else>
                        <td :colspan="(tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length * 4) + 2" class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก หรือช่วงเวลาที่เลือกไม่ต่อเนื่อง</td>
                    </tr>
                </tbody>
            </v-table>
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
                
                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟสัดส่วนมูลค่ารวม แยกตามมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart 
                                id="polarChartPrice" 
                                type="polarArea" 
                                :options="{...polarChartOptions, labels: polarChartPriceData.labels}" 
                                :series="polarChartPriceData.series" 
                                height="400" 
                            />
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟสัดส่วนมูลค่ารวม แยกตามภูมิภาค</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart 
                                id="polarChartRegion" 
                                type="polarArea" 
                                :options="{...polarChartOptions, labels: polarChartRegionData.labels}" 
                                :series="polarChartRegionData.series" 
                                height="400" 
                            />
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">รายงานสถานะการกรอกสัญญาของสมาชิก</h3>
                            <h5 class="card-subtitle">ข้อมูลอ้างอิง: สมาชิกประเภท User ทั้งหมด</h5>
                            <v-row class="mt-4">
                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" color="primary">
                                        <v-card-title class="text-center text-subtitle-1 pt-4 pb-0">สถานะการกรอกสัญญารวม</v-card-title>
                                        <v-card-text class="pa-2">
                                            <apexchart 
                                                id="donutChartMember" 
                                                type="donut" 
                                                :options="donutChartOptions" 
                                                :series="memberSubmissionSummary.donutData" 
                                                height="350" 
                                            />
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-table density="compact" class="mt-4 border">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="text-center text-subtitle-1">สรุปจำนวนสมาชิก</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-blue-grey-lighten-5">
                                                <td class="font-weight-bold">สมาชิกทั้งหมด (รวมผู้ดูแล)</td>
                                                <td class="text-right font-weight-bold">{{ memberSubmissionSummary.totalUsersIncludingAdmin }} คน</td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-bold">สมาชิกทั้งหมด (ตัดผู้ดูแล)</td>
                                                <td class="text-right font-weight-bold">{{ memberSubmissionSummary.totalUsers }} คน</td>
                                            </tr>
                                            <tr>
                                                <td>สมาชิกที่เคยกรอกสัญญา (ทั้งหมด)</td>
                                                <td class="text-right">{{ memberSubmissionSummary.submittedTotal }} คน</td>
                                            </tr>
                                            <tr v-if="memberSubmissionSummary.yearA">
                                                <td>สมาชิกที่กรอกสัญญา ปี {{ memberSubmissionSummary.yearA }}</td>
                                                <td class="text-right">{{ memberSubmissionSummary.submittedYearA }} คน</td>
                                            </tr>
                                            <tr v-if="memberSubmissionSummary.yearB">
                                                <td>สมาชิกที่กรอกสัญญา ปี {{ memberSubmissionSummary.yearB }}</td>
                                                <td class="text-right">{{ memberSubmissionSummary.submittedYearB }} คน</td>
                                            </tr>
                                            <tr class="bg-red-lighten-5">
                                                <td class="font-weight-bold text-error">สมาชิกที่ยังไม่ได้กรอกสัญญาเลย</td>
                                                <td class="text-right font-weight-bold text-error">{{ memberSubmissionSummary.notSubmittedTotal }} คน</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-col>
                                
                                <v-col cols="12" md="4">
                                    <v-card>
                                        <v-card-title class="text-center text-subtitle-1 pt-4 pb-0">จำนวนสัญญาที่กรอกต่อรายสมาชิก (ปีที่เลือก)</v-card-title>
                                        <v-card-text class="pa-2">
                                            <apexchart 
                                                id="barChartMember" 
                                                type="bar" 
                                                :options="barChartOptions" 
                                                :series="memberListChartData.series" 
                                                :height="barChartOptions.chart.height"
                                            />
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสถานะการกรอกสัญญาต่อเดือน (แยกตามรายสมาชิก)</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            
                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 200px;">รายชื่อสมาชิก</th>
                                        <th :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length" class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th 
                                            v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')" 
                                            :key="period.key" 
                                            class="text-center text-subtitle-1" 
                                            style="min-width: 80px;"
                                        >
                                            {{ period.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="memberMonthlySubmissionTableData.length > 0">
                                        <tr v-for="member in memberMonthlySubmissionTableData" :key="member.name">
                                            <td class="text-left font-weight-bold text-caption border-e">{{ member.name }}</td>
                                            
                                            <td 
                                                v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')" 
                                                :key="period.key"
                                                class="text-center text-subtitle-2"
                                                :class="{'text-success font-weight-bold': member.submissions[period.key] === 'X', 'text-error': member.submissions[period.key] === '-'}"
                                            >
                                                {{ member.submissions[period.key] }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 1" class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลสมาชิกที่แสดงผลตามเงื่อนไข</td>
                                    </tr>
                                </tbody>
                            </v-table>
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

                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            
                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 150px;">ภูมิภาค</th>
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
                                    <template v-if="regionReportTableData.length > 0">
                                        <template v-for="(region, regIndex) in regionReportTableData" :key="region.categoryName">
                                            <tr 
                                                v-for="(row, rowIndex) in region.rows" 
                                                :key="`${region.categoryName}-${row.metricKey}`"
                                                :class="{ 
                                                    'bg-blue-grey-lighten-5': region.categoryName === 'รวมทั่วประเทศ',
                                                    'border-t': rowIndex === 0,
                                                }"
                                            >
                                                <td v-if="rowIndex === 0" 
                                                    :rowspan="region.rows.length"
                                                    class="text-left font-weight-bold text-subtitle-2 border-e"
                                                    :class="{'text-primary': region.categoryName === 'รวมทั่วประเทศ'}"
                                                >
                                                    {{ region.categoryName }}
                                                </td>

                                                <td class="text-left text-caption border-e">{{ row.metricName }}</td>
                                                
                                                <td v-for="period in tablePeriods" 
                                                    :key="period.key"
                                                    class="text-right text-subtitle-2"
                                                    :class="{'text-primary font-weight-bold': region.categoryName === 'รวมทั่วประเทศ' && row.metricKey === 'total_value', 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key}"
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

                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">ตารางสรุปยอดเซ็นสัญญา แยกตามภูมิภาค และมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            
                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e" style="min-width: 150px;">ภูมิภาค</th>
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
                                    <template v-if="regionAndCategoryReportTableData.length > 0">
                                        <template v-for="(regionGroup, regIndex) in regionAndCategoryReportTableData" :key="regionGroup.regionName">
                                            <template v-for="(category, catIndex) in regionGroup.categories" :key="`${regionGroup.regionName}-${category.categoryName}`">
                                                <tr 
                                                    v-for="(row, rowIndex) in category.rows" 
                                                    :key="`${regionGroup.regionName}-${category.categoryName}-${row.metricKey}`"
                                                    :class="{ 
                                                        // ไฮไลต์แถว 'รวมทั่วประเทศ' หรือ แถว 'รวม' ของมูลค่าบ้าน
                                                        'bg-blue-grey-lighten-5': regionGroup.regionName === 'รวมทั่วประเทศ' || category.categoryName === 'รวม',
                                                        'border-t': rowIndex === 0 && catIndex === 0,
                                                        'border-t-sm': rowIndex === 0 && category.categoryName === 'รวม',
                                                    }"
                                                >
                                                    <td v-if="rowIndex === 0 && catIndex === 0" 
                                                        :rowspan="regionGroup.categories.length * category.rows.length"
                                                        class="text-left font-weight-bold text-subtitle-2 border-e"
                                                        :class="{'text-primary': regionGroup.regionName === 'รวมทั่วประเทศ'}"
                                                    >
                                                        {{ regionGroup.regionName }}
                                                    </td>
                                                    
                                                    <td v-if="rowIndex === 0" 
                                                        :rowspan="category.rows.length"
                                                        class="text-left font-weight-bold text-caption border-e"
                                                        :class="{'text-primary': category.categoryName === 'รวม'}"
                                                    >
                                                        {{ category.categoryName }}
                                                    </td>

                                                    <td class="text-left text-caption border-e">{{ row.metricName }}</td>
                                                    
                                                    <td v-for="period in tablePeriods" 
                                                        :key="period.key"
                                                        class="text-right text-subtitle-2"
                                                        :class="{'text-primary font-weight-bold': (regionGroup.regionName === 'รวมทั่วประเทศ' || category.categoryName === 'รวม') && row.metricKey === 'total_value', 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key}"
                                                    >
                                                        {{ (row.data[period.key] || 0) !== 0 ? row.format(row.data[period.key] || 0) : '-' }}
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 3" class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
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