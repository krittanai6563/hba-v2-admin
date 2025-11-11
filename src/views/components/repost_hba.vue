<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useDate } from 'vuetify/lib/framework.mjs';
const date = useDate();
const tab = ref('monthly');

interface Metrics {
    total_value: number;
    total_area: number;
    total_units: number;
    average_price_per_sqm: number;
}

interface MemberSubmission {
    member_id: string;
    name: string;
    role: 'user' | 'admin' | 'master';
    member_type: string; // <-- เพิ่มบรรทัดนี้
    total_submitted_count: number;
    submissions_by_year: Record<string, number>;
    submissions_in_period: Record<string, number[]>;
}

interface SummaryData {
    yearly_data: Record<string, Record<string, Metrics>>;
    monthly_data: Record<string, Record<number, Record<string, Metrics>>>;
    quarterly_data?: Record<string, Record<number, Record<string, Metrics>>>;
    region_data?: Record<string, Record<number, Record<string, Record<string, Metrics>>>>;
    membership_data?: MemberSubmission[];
}

const availableMonths = computed(() => {
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    const selectedYears = selectyear.value;

    const isCurrentYearInContext =
        selectedYears.length === 0 ||
        selectedYears.includes(currentBuddhistYear);

    if (isCurrentYearInContext) {
        return Months.slice(0, currentMonthIndex);
    } else {
        // ถ้าไม่ใช่ (คือเลือกเฉพาะปีในอดีต): ให้แสดง 12 เดือนเต็ม
        return Months;
    }
});

const selectyear = ref<string[]>([]);
const selectMonths = ref<string[]>([]);
const selectQuarters = ref<string[]>([]);
const year = ['2568', '2567'];
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
const MonthAbbreviations = [
    'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
    'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
];
const Quarters = [
    { name: 'ไตรมาส 1', index: 1, months: [1, 2, 3], names: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม'] },
    { name: 'ไตรมาส 2', index: 2, months: [4, 5, 6], names: ['เมษายน', 'พฤษภาคม', 'มิถุนายน'] },
    { name: 'ไตรมาส 3', index: 3, months: [7, 8, 9], names: ['กรกฎาคม', 'สิงหาคม', 'กันยายน'] },
    { name: 'ไตรมาส 4', index: 4, months: [10, 11, 12], names: ['ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'] },
];
const quarterMap = Quarters.reduce((acc, q) => { acc[q.name] = q.index; return acc; }, {} as Record<string, number>);

const regionCategories = [
    'กรุงเทพปริมณฑล',
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
const categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป', 'รวม'];
const valueCategories = categoryOrder.filter(cat => cat !== 'รวม');
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

const quartersToMonthsNames = computed<string[]>(() => {
    if (selectQuarters.value.length === 0) return [];

    let monthIndices: number[] = [];
    selectQuarters.value.forEach(qName => {
        const quarter = Quarters.find(q => q.name === qName);
        if (quarter) monthIndices.push(...quarter.months);
    });
    const uniqueMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
    return uniqueMonthIndices.map(index => Months[index - 1]);
});

const getCurrentPeriod = () => {
    const today = new Date();
    const currentBuddhistYear = (date as any).format(today, 'yyyy', { locale: 'th-TH' });
    const currentMonthIndex = today.getMonth() + 1;
    return { currentBuddhistYear, currentMonthIndex };
};

onMounted(() => {
    const { currentBuddhistYear } = getCurrentPeriod();
    if (!selectyear.value || selectyear.value.length === 0) {
        if (year.includes(currentBuddhistYear)) {
            selectyear.value = [currentBuddhistYear];
        }
    }
    // ส่งค่า state ปัจจุบันเข้าไป
    fetchSummary(selectyear.value, selectMonths.value, selectQuarters.value);
});

const fetchSummary = async (
    currentYears: string[],
    currentMonths: string[],
    currentQuarters: string[]
) => {
    // 1. ใช้ค่าที่ส่งเข้ามา (arguments)
    if (!currentYears || currentYears.length === 0) {
        console.error('Please select at least one year.');
        summaryData.value = null;
        chartSeries.value = [];
        return;
    }
    const data: any = {
        user_id: userId,
        buddhist_year: currentYears, // <--- ใช้ค่าที่ส่งเข้ามา
        role: userRole
    };

    // 2. สร้างตรรกะ "รวมเดือน" *ข้างในนี้*
    // เพื่อให้แน่ใจว่าใช้ค่าที่สดใหม่
    let indices: number[] = [];
    if (currentQuarters.length > 0) {
        currentQuarters.forEach(qName => {
            const quarter = Quarters.find(q => q.name === qName);
            if (quarter) indices.push(...quarter.months);
        });
    }
    const manualMonthIndices = currentMonths.map(m => monthMap[m]).filter(Boolean) as number[];
    if (manualMonthIndices.length > 0) {
        indices.push(...manualMonthIndices);
    }

    const monthsToFetch = Array.from(new Set(indices)).sort((a, b) => a - b);
    const quartersToFetch = currentQuarters.map((quarterName: string) => quarterMap[quarterName] || null).filter(Boolean);
    // --- สิ้นสุดตรรกะ "รวมเดือน" ---

    // 3. ส่งข้อมูล (ที่ถูกต้อง) ไป PHP
    if (monthsToFetch.length > 0) {
        data.months = monthsToFetch;
    }
    if (quartersToFetch.length > 0) {
        data.quarters = quartersToFetch;
    }

    console.log('Sending data to backend:', data); // <-- บรรทัดนี้จะแสดงค่าที่ถูกต้อง

    try {
        // ... (rest of fetch logic is unchanged) ...
        // (โค้ด try...catch... ของคุณ)
        const res = await fetch('https://uat.hba-sales.org/backend/repost_admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (!res.ok) {

            const errorText = await res.text();
            console.error(`❌ HTTP Error: ${res.status} ${res.statusText}`, errorText);
            throw new Error(`Server responded with ${res.status}: ${errorText}`);
        }

        const responseData: SummaryData = await res.json();

        summaryData.value = responseData;
        updateChartData(responseData);

    } catch (err) {
        console.error('❌ Error fetching summary:', err);
        summaryData.value = null;
        chartSeries.value = [];
    }
}; //

// 🚀 START: 2. (ลบ) targetMonthIndices
// (ลบ `const targetMonthIndices = ...` บล็อกเก่าทิ้งไปทั้งหมด)
// 🚀 END: 2. (ลบ) targetMonthIndices

// 🚀 START: 3. (เพิ่ม) "ศูนย์กลางความจริง"
// สร้าง `computed` ตัวใหม่นี้ขึ้นมาแทนที่ตัวเก่า
// (ลบบล็อก const targetMonthIndices... ของเก่าทิ้งไปก่อน)

// ⬇️ วางโค้ด "ศูนย์กลาง" ใหม่นี้เข้าไปแทน (ประมาณบรรทัด 202) ⬇️
// (ลบบล็อก const targetMonthIndices... ของเก่าทิ้งไปก่อน)

// ⬇️ วางโค้ด "ศูนย์กลาง" ใหม่นี้เข้าไปแทน (ประมาณบรรทัด 202) ⬇️
const combinedTargetMonthIndices = computed<number[]>(() => {
    let indices: number[] = [];

    // 1. ดึงเดือนจาก "ไตรมาส" ที่เลือก
    const selectedQuarters = selectQuarters.value;
    if (selectedQuarters.length > 0) {
        selectedQuarters.forEach(qName => {
            const quarter = Quarters.find(q => q.name === qName);
            if (quarter) indices.push(...quarter.months);
        });
    }

    // 2. ดึงเดือนจาก "เดือน" ที่เลือก
    const manualMonthIndices = selectMonths.value.map(m => monthMap[m]).filter(Boolean) as number[];
    if (manualMonthIndices.length > 0) {
        indices.push(...manualMonthIndices);
    }

    // 3. คืนค่าเป็นรายการเดือนที่ไม่ซ้ำกัน และเรียงลำดับแล้ว
    return Array.from(new Set(indices)).sort((a, b) => a - b);
});

const updateChartData = (data: SummaryData) => {
    const finalSeries: any[] = [];
    const dataForAverageCalc: number[][] = [];
    let finalCategories: string[] = categoryOrder;


    const sortedYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const selectedYears = sortedYears;

    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value;
    const getValue = (dataObj: Metrics | undefined) => (dataObj?.total_value || 0);

    // 🚀 START: 4. (แก้ไข) updateChartData
    // (ลบฟังก์ชัน `getSelectedMonthIndices` ที่ซ้ำซ้อนทิ้งไป)
    /*
    const getSelectedMonthIndices = () => {
        ... (ตรรกะ if/else ที่ผิด) ...
    };
    */

    const targetMonths = combinedTargetMonthIndices.value; // <--- ใช้ "ศูนย์กลาง"
    // 🚀 END: 4. (แก้ไข) updateChartData


    if (selectedYears.length === 1 && (selectedMonths.length > 1 || selectedQuarters.length > 0 || targetMonths.length > 1)) { // <-- ปรับเงื่อนไข

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
    } else if (selectedYears.length > 1 && (selectedMonths.length > 0 || selectedQuarters.length > 0 || targetMonths.length > 0)) { // <-- ปรับเงื่อนไข

        finalCategories = categoryOrder;
        const monthsToDisplay = targetMonths; // <--- ใช้ targetMonths

        monthsToDisplay.forEach((monthIndex) => {
            const monthName = Months[monthIndex - 1];


            selectedYears.forEach((year) => {
                const monthlyData = categoryOrder.map((category) => getValue(data.monthly_data[year]?.[monthIndex]?.[category]));

                dataForAverageCalc.push(monthlyData);
                finalSeries.push({ name: `${monthName} ${year}`, type: 'column', data: monthlyData });
            });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 0 && selectedQuarters.length === 0) {

        finalCategories = categoryOrder;
        const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[selectedYears[0]]?.[category]));
        dataForAverageCalc.push(yearlyData);
        finalSeries.push({ name: `ปี ${selectedYears[0]}`, type: 'column', data: dataForAverageCalc[0] });
    } else if (selectedMonths.length === 0 && selectedQuarters.length === 0 && selectedYears.length > 1) {

        finalCategories = categoryOrder;

        selectedYears.forEach((year) => {
            const yearlyData = categoryOrder.map((category) => getValue(data.yearly_data[year]?.[category]));
            dataForAverageCalc.push(yearlyData);
            finalSeries.push({ name: `ปี ${year}`, type: 'column', data: yearlyData });
        });
    } else if (selectedYears.length === 1 && selectedMonths.length === 1 && selectedQuarters.length === 0 && targetMonths.length === 1) { // <-- ปรับเงื่อนไข

        finalCategories = categoryOrder;
        const selectedYear = selectedYears[0];
        const monthIndex = targetMonths[0]; // <--- ใช้ targetMonths

        const monthlyData = categoryOrder.map((category) => getValue(data.monthly_data[selectedYear]?.[monthIndex]?.[category]));
        dataForAverageCalc.push(monthlyData);
        finalSeries.push({ name: `${Months[monthIndex - 1]} ${selectedYear}`, type: 'column', data: monthlyData });
    }


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

    const sortedYears = [...selectyear.value].sort((a, b) => a.localeCompare(b, 'th-TH'));
    const firstYear = sortedYears[0];
    const lastYear = sortedYears[sortedYears.length - 1];

    const selectedMonths = selectMonths.value;
    const selectedQuarters = selectQuarters.value;

    const yearText = sortedYears.length === 1 ? `ปี ${firstYear}` :
        sortedYears.length > 1 ? `ปี ${firstYear} - ปี ${lastYear}` : '';

    // 🚀 START: 5. (แก้ไข) chartSubtitle
    // (ลบตรรกะ if/else ที่ผิดพลาดทิ้ง)
    /*
    let monthIndices: number[] = [];
    if (selectedQuarters.length > 0) {
        ...
    } else if (selectedMonths.length > 0) {
        ...
    }
    */
    let monthIndices: number[] = combinedTargetMonthIndices.value; // <--- ใช้ "ศูนย์กลาง"
    // 🚀 END: 5. (แก้ไข) chartSubtitle

    const sortedMonthIndices = Array.from(new Set(monthIndices)).sort((a, b) => a - b);
    const firstMonthIndex = sortedMonthIndices[0];
    const lastMonthIndex = sortedMonthIndices[sortedMonthIndices.length - 1];
    const firstMonthName = firstMonthIndex ? Months[firstMonthIndex - 1] : '';
    const lastMonthName = lastMonthIndex ? Months[lastMonthIndex - 1] : '';


    if (selectedQuarters.length > 0) {
        const quarterNames = selectedQuarters.join(', ');

        if (firstMonthName && lastMonthName) {

            return `ไตรมาส: ${quarterNames} (${firstMonthName} - ${lastMonthName}) ${yearText}`;
        }
        return `ไตรมาส: ${quarterNames} ${yearText}`;
    }


    if (sortedMonthIndices.length > 1) {


        if (sortedYears.length === 1) {
            const monthIndicesString = sortedMonthIndices.join(',');
            const Q = Quarters.find(q => q.months.join(',') === monthIndicesString);

            if (Q) {

                return `${yearText} - ${Q.name} (${Q.names.join(' - ')})`;
            }
        }


        if (sortedYears.length === 1) {
            return `เดือน ${firstMonthName} - ${lastMonthName} ปี ${firstYear}`;
        } else if (sortedYears.length > 1) {

            return `เดือน ${firstMonthName} - ${lastMonthName} ปี ${firstYear} - ปี ${lastYear}`;
        }
    }

    else if (sortedMonthIndices.length === 1) {
        const selectedMonthName = firstMonthName;
        if (sortedYears.length === 1) {
            return `เดือน ${selectedMonthName} ปี ${firstYear}`;
        } else if (sortedYears.length > 1) {

            return `เดือน ${selectedMonthName} ปี ${firstYear} - ปี ${lastYear}`;
        }
    }

    else if (sortedYears.length > 0) {
        if (sortedYears.length === 1) {
            return `สรุปรายปี ${firstYear}`;
        } else {
            return `เปรียบเทียบรายปี ${firstYear} - ปี ${lastYear}`;
        }
    }


    const { currentBuddhistYear } = getCurrentPeriod();
    const currentMonthName = Months[new Date().getMonth()];


    if (sortedYears.length === 1 && sortedYears[0] === currentBuddhistYear) {
        return `สรุปยอดเซ็นสัญญา ถึงเดือน ${currentMonthName} ปี ${currentBuddhistYear}`;
    }

    return 'กรุณาเลือกข้อมูลเพื่อแสดงผล';
});


// 🚀 START: 6. (ลบ) Watcher ที่ไม่จำเป็น
// (ลบ Watcher ที่บังคับ selectMonths ทิ้ง)
/*
watch(selectQuarters, (newQuarters) => {
    if (newQuarters.length > 0) {
        selectMonths.value = quartersToMonthsNames.value;
    }
}, { immediate: false });
*/
// 🚀 END: 6. (ลบ) Watcher ที่ไม่จำเป็น

watch(
    [selectyear, selectMonths, selectQuarters],
    // 1. รับค่าใหม่ ([newYears, newMonths, newQuarters])
    ([newYears, newMonths, newQuarters]) => {
        // 2. ส่งค่าที่สดใหม่เข้าไปใน fetchSummary
        fetchSummary(newYears, newMonths, newQuarters);
    },
    { immediate: false, deep: true } // 3. เพิ่ม deep: true เพื่อความแน่นอน
);

const chartOptions = ref({

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



interface TableCellData {
    [key: string]: number;
}
interface TableRow {
    metricKey: keyof Metrics | 'average_price_per_sqm';
    metricName: string;
    format: (v: number) => string;
    data: TableCellData;
    growth: {
        mom: number | null;
        ytd: number | null;
    }
}
interface TableCategory {
    categoryName: string;
    rows: TableRow[];
}


const showQoQ = computed(() => {
    //  START: *** V.2 ***
    // ตรวจสอบว่ามีเดือนสุดท้ายของไตรมาสที่ถูกเลือกหรือไม่
    const targetMonths = combinedTargetMonthIndices.value;
    if (targetMonths.length === 0) return false;

    // ตรวจสอบว่าในเดือนที่เลือก มีเดือนที่เป็นเดือนสุดท้ายของไตรมาส (3, 6, 9, 12) หรือไม่
    const hasQuarterEndMonth = targetMonths.some(monthIndex =>
        Quarters.some(q => q.months[q.months.length - 1] === monthIndex)
    );

    return hasQuarterEndMonth;
    // END: *** V.2 ***
});

const getRegionalMetrics = (period: typeof tablePeriods.value[0], region: string, category: string): Metrics => {
    let metrics: Metrics | undefined;


    if (region === 'รวมทั่วประเทศ') {
        if (period.monthIndex && period.monthIndex !== 0) {

            metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
        } else if (!period.monthIndex && period.year !== 'TOTAL') {

            metrics = summaryData.value?.yearly_data[period.year]?.[category];
        }
    }


    if (!metrics && region !== 'รวมทั่วประเทศ') {

        const regionDataForYear = summaryData.value?.region_data?.[period.year];

        if (period.monthIndex && period.monthIndex !== 0) {

            metrics = regionDataForYear?.[period.monthIndex]?.[region]?.[category];
        }


        else if (!period.monthIndex && period.year !== 'TOTAL' && regionDataForYear) {


            let annualMetrics: Metrics = {
                total_value: 0,
                total_area: 0,
                total_units: 0,
                average_price_per_sqm: 0
            };
            let foundData = false;


            const monthKeys = Object.keys(regionDataForYear);

            for (const monthKey of monthKeys) {
                const monthIndexAsNumber = parseInt(monthKey);

                const monthlyMetrics = regionDataForYear[monthIndexAsNumber]?.[region]?.[category];

                if (monthlyMetrics) {
                    annualMetrics.total_value += monthlyMetrics.total_value;
                    annualMetrics.total_area += monthlyMetrics.total_area;
                    annualMetrics.total_units += monthlyMetrics.total_units;
                    foundData = true;
                }
            }

            if (foundData) {

                annualMetrics.average_price_per_sqm = (annualMetrics.total_area > 0) ? (annualMetrics.total_value / annualMetrics.total_area) : 0;
                metrics = annualMetrics;
            }
        }
    }


    if (!metrics) {
        return { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
    }
    return metrics;
};


const tablePeriods = computed(() => {
    const selectedYears = selectyear.value;
    const { currentBuddhistYear, currentMonthIndex } = getCurrentPeriod();
    let periods: { key: string, label: string, year: string, monthIndex?: number }[] = [];
    const sortedYears = [...selectedYears].sort((a, b) => a.localeCompare(b, 'th-TH'));

    const currentTargetMonthIndices = combinedTargetMonthIndices.value;

    if (currentTargetMonthIndices.length > 0) {
        // --- CASE 1: ผู้ใช้เลือกเดือน (หรือไตรมาส) ---
        // (ตรรกะนี้ถูกต้อง)
        const yearsToProcess = sortedYears.length > 0 ? sortedYears : [currentBuddhistYear];

        yearsToProcess.forEach(year => {
            currentTargetMonthIndices.forEach(monthIndex => {
                periods.push({
                    key: `M${monthIndex}Y${year}`,
                    label: `${MonthAbbreviations[monthIndex - 1]} ${year.substring(2, 4)}`,
                    year: year,
                    monthIndex: monthIndex
                });
            });
        });
    } else {
        // --- CASE 2: ผู้ใช้ไม่ได้เลือกเดือน (เลือกเฉพาะปี หรือ ไม่เลือกอะไรเลย) ---

        if (sortedYears.length > 0) {
            // --- Subcase 2A: ผู้ใช้ "เลือกปี" (เช่น "2567" หรือ "2567, 2566") ---
            sortedYears.forEach(year => {

                // ⬇️⬇️⬇️ นี่คือตรรกะที่แก้ไข ⬇️⬇️⬇️
                // ตรวจสอบว่าปีที่กำลังวนลูป คือปีปัจจุบันหรือไม่
                const isCurrent = (year === currentBuddhistYear);
                // ถ้าใช่ ให้วนลูปถึงเดือนปัจจุบัน, ถ้าไม่ใช่ (เป็นปีอดีต) ให้วน 12 เดือน
                const loopEnd = isCurrent ? currentMonthIndex : 12;

                for (let i = 1; i <= loopEnd; i++) {
                    periods.push({
                        key: `M${i}Y${year}`,
                        label: `${MonthAbbreviations[i - 1]} ${year.substring(2, 4)}`,
                        year: year,
                        monthIndex: i
                    });
                }
                // ⬆️⬆️⬆️ สิ้นสุดตรรกะที่แก้ไข ⬆️⬆️⬆️
            });
        } else {
            // --- Subcase 2B: ผู้ใช้ "ไม่เลือกอะไรเลย" (Default ตอนโหลดหน้า) ---
            // (ตรรกะนี้ถูกต้องอยู่แล้ว คือแสดงถึงเดือนปัจจุบัน)
            if (year.includes(currentBuddhistYear)) {
                for (let i = 1; i <= currentMonthIndex; i++) {
                    periods.push({
                        key: `M${i}Y${currentBuddhistYear}`,
                        label: `${MonthAbbreviations[i - 1]} ${currentBuddhistYear.substring(2, 4)}`,
                        year: currentBuddhistYear,
                        monthIndex: i
                    });
                }
            }
        }
    }

    // --- ส่วนการเรียงลำดับ และการเพิ่ม 'รวมทุกช่วง' (อันนี้ถูกต้องแล้ว) ---
    periods.sort((a, b) => {
        if (a.year !== b.year) {
            return a.year.localeCompare(b.year, 'th-TH');
        }
        const monthA = a.monthIndex || 0;
        const monthB = a.monthIndex || 0;
        return monthA - monthB;
    });

    let finalPeriods = periods;

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



const monthlyReportTableData = computed<TableCategory[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';
    const allCategories = [...valueCategories, 'รวม'];

    // ดึง "ช่วงเวลาสุดท้าย" ที่เลือก (จาก computed ที่มีอยู่)
    const lp = lastPeriod.value;

    const finalTable: TableCategory[] = [];

    allCategories.forEach(categoryName => {
        const categoryData: TableCategory = {
            categoryName: categoryName,
            rows: []
        };

        metricRows.forEach(metric => {
            const row: TableRow = {
                metricKey: metric.key as keyof Metrics | 'average_price_per_sqm',
                metricName: metric.name,
                format: metric.format,
                data: {},
                // ⬅️ เพิ่ม object เริ่มต้นสำหรับ growth
                growth: { mom: null, ytd: null }
            };

            let totalMetricValue = 0;
            let totalValueForAvg = 0;
            let totalAreaForAvg = 0;

            // --- 1. คำนวณค่าดิบ (เหมือนเดิม) ---
            currentPeriods.forEach(p => {
                if (p.key === grandTotalPeriodKey) return;

                const periodKey = p.key;

                // Helper (จากโค้ดเดิมของคุณ)
                const getMetrics = (period: typeof currentPeriods[0], category: string): Metrics => {
                    let metrics: Metrics | undefined;
                    if (period.monthIndex && period.monthIndex !== 0) {
                        metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
                    } else if (!period.monthIndex && period.year !== 'TOTAL') {
                        metrics = summaryData.value?.yearly_data[period.year]?.[category];
                    }
                    if (!metrics) return { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
                    return metrics;
                };

                const metrics = getMetrics(p, categoryName);
                const metricValue: number = metrics[metric.key as keyof Metrics] || 0;
                row.data[periodKey] = metricValue;

                // สะสมยอดสำหรับ 'รวมทุกช่วง'
                if (metric.key !== 'average_price_per_sqm') {
                    totalMetricValue += metricValue;
                }
                totalValueForAvg += metrics.total_value;
                totalAreaForAvg += metrics.total_area;
            });

            // ใส่ค่ายอดรวม 'รวมทุกช่วง'
            if (currentPeriods.some(p => p.key === grandTotalPeriodKey)) {
                let grandTotalMetricValue: number;
                if (metric.key === 'average_price_per_sqm') {
                    grandTotalMetricValue = totalAreaForAvg > 0 ? (totalValueForAvg / totalAreaForAvg) : 0;
                } else {
                    grandTotalMetricValue = totalMetricValue;
                }
                row.data[grandTotalPeriodKey] = grandTotalMetricValue;
            }

            // --- 2. ⬇️ ส่วนที่เพิ่มใหม่: คำนวณ MoM% และ YTD% ⬇️ ---
            if (lp && lp.monthIndex) { // คำนวณเมื่อ "ช่วงเวลาสุดท้าย" เป็น "เดือน"
                const currentYear = lp.year;
                const currentMonth = lp.monthIndex;
                const prevYear = (parseInt(currentYear) - 1).toString();

                // Helper: ดึง Metrics ของเดือนใดๆ
                const getMetricsForPeriod = (year: string, month: number, category: string): Metrics => {
                    const metrics = summaryData.value?.monthly_data[year]?.[month]?.[category];
                    return metrics || { total_value: 0, total_area: 0, total_units: 0, average_price_per_sqm: 0 };
                };

                // Helper: ดึงค่ายอดรวม YTD (ที่ปรับปรุงให้ใช้ได้กับทุก metric)
                const getAggregatedValueForMetric = (year: string, startMonth: number, endMonth: number, category: string, metricKey: keyof Metrics): number => {
                    let sum = 0;
                    const monthlyData = summaryData.value?.monthly_data[year];
                    if (!monthlyData) return 0;
                    for (let month = startMonth; month <= endMonth; month++) {
                        const metrics = monthlyData[month]?.[category];
                        if (metrics) {
                            sum += metrics[metricKey] || 0;
                        }
                    }
                    return sum;
                };

                // --- คำนวณค่าสำหรับ MoM ---
                const currentMetrics = getMetricsForPeriod(currentYear, currentMonth, categoryName);
                const currentValue = currentMetrics[metric.key as keyof Metrics] || 0;

                const prevMonth = (currentMonth === 1) ? 12 : currentMonth - 1;
                const prevMonthYear = (currentMonth === 1) ? prevYear : currentYear;
                const prevMonthMetrics = getMetricsForPeriod(prevMonthYear, prevMonth, categoryName);
                const prevMonthValue = prevMonthMetrics[metric.key as keyof Metrics] || 0;

                // --- คำนวณค่าสำหรับ YTD (แยกตรรกะสำหรับ avg) ---
                let currentYTDValue: number;
                let prevYTDValue: number;

                if (metric.key === 'average_price_per_sqm') {
                    const currentYTD_Value = getAggregatedValueForMetric(currentYear, 1, currentMonth, categoryName, 'total_value');
                    const currentYTD_Area = getAggregatedValueForMetric(currentYear, 1, currentMonth, categoryName, 'total_area');
                    currentYTDValue = (currentYTD_Area > 0) ? (currentYTD_Value / currentYTD_Area) : 0;

                    const prevYTD_Value = getAggregatedValueForMetric(prevYear, 1, currentMonth, categoryName, 'total_value');
                    const prevYTD_Area = getAggregatedValueForMetric(prevYear, 1, currentMonth, categoryName, 'total_area');
                    prevYTDValue = (prevYTD_Area > 0) ? (prevYTD_Value / prevYTD_Area) : 0;
                } else {
                    currentYTDValue = getAggregatedValueForMetric(currentYear, 1, currentMonth, categoryName, metric.key as keyof Metrics);
                    prevYTDValue = getAggregatedValueForMetric(prevYear, 1, currentMonth, categoryName, metric.key as keyof Metrics);
                }

                // --- บันทึกค่า Growth ---
                if (prevMonthValue !== 0) {
                    row.growth.mom = ((currentValue / prevMonthValue) - 1) * 100;
                } else if (currentValue > 0) {
                    row.growth.mom = 100; // กรณียอดเก่าเป็น 0
                }

                if (prevYTDValue !== 0) {
                    row.growth.ytd = ((currentYTDValue / prevYTDValue) - 1) * 100;
                } else if (currentYTDValue > 0) {
                    row.growth.ytd = 100; // กรณียอดเก่าเป็น 0
                }
            }
            // --- ⬆️ จบส่วนที่เพิ่มใหม่ ⬆️ ---

            categoryData.rows.push(row);
        });
        finalTable.push(categoryData);
    });

    return finalTable;
});


const regionReportTableData = computed<TableCategory[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';

    const finalTable: TableCategory[] = [];


    regionCategories.forEach(regionName => {
        const categoryData: TableCategory = {
            categoryName: regionName,
            rows: []
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


            currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                const periodKey = p.key;



                const metrics = getRegionalMetrics(p, regionName, 'รวม');

                let metricValue: number = metrics[metric.key as keyof Metrics] || 0;

                row.data[periodKey] = metricValue;


                if (metric.key !== 'average_price_per_sqm') {
                    totalMetricValue += metricValue;
                }
                totalValueForAvg += metrics.total_value;
                totalAreaForAvg += metrics.total_area;
            });


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


interface RegionCategoryGroup {
    regionName: string;
    categories: TableCategory[];
}


const regionAndCategoryReportTableData = computed<RegionCategoryGroup[]>(() => {
    if (!summaryData.value) {
        return [];
    }

    const currentPeriods = tablePeriods.value;
    const grandTotalPeriodKey = 'TOTAL_PERIODS';

    const allPriceCategories = [...valueCategories, 'รวม'];

    const finalTable: RegionCategoryGroup[] = [];


    regionCategories.forEach(regionName => {
        const regionGroup: RegionCategoryGroup = {
            regionName: regionName,
            categories: []
        };


        allPriceCategories.forEach(categoryName => {
            const categoryData: TableCategory = {
                categoryName: categoryName,
                rows: []
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


                currentPeriods.filter(p => p.key !== grandTotalPeriodKey).forEach(p => {
                    const periodKey = p.key;



                    const metrics = getRegionalMetrics(p, regionName, categoryName);

                    let metricValue: number = metrics[metric.key as keyof Metrics] || 0;

                    row.data[periodKey] = metricValue;


                    if (metric.key !== 'average_price_per_sqm') {
                        totalMetricValue += metricValue;
                    }
                    totalValueForAvg += metrics.total_value;
                    totalAreaForAvg += metrics.total_area;
                });


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



const polarChartPriceData = computed(() => {
    const data = monthlyReportTableData.value;
    if (!data || data.length === 0) {
        return { series: [], labels: [] };
    }

    const priceLabels = valueCategories;
    const seriesData: number[] = [];

    priceLabels.forEach(categoryName => {

        const categoryGroup = data.find(c => c.categoryName === categoryName);
        if (categoryGroup) {

            const totalValueRow = categoryGroup.rows.find(r => r.metricKey === 'total_value');



            const periodKey = totalValueRow?.data['TOTAL_PERIODS'] !== undefined
                ? 'TOTAL_PERIODS'
                : tablePeriods.value[0]?.key;

            const totalValue = (periodKey ? totalValueRow?.data[periodKey] : 0) || 0;


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


const polarChartRegionData = computed(() => {
    const data = regionReportTableData.value;
    if (!data || data.length === 0) {
        return { series: [], labels: [] };
    }


    const regionLabels = regionCategories.filter(reg => reg !== 'รวมทั่วประเทศ');
    const seriesData: number[] = [];

    regionLabels.forEach(regionName => {
        const regionGroup = data.find(c => c.categoryName === regionName);
        if (regionGroup) {
            const totalValueRow = regionGroup.rows.find(r => r.metricKey === 'total_value');


            const periodKey = totalValueRow?.data['TOTAL_PERIODS'] !== undefined
                ? 'TOTAL_PERIODS'
                : tablePeriods.value[0]?.key;

            const totalValue = (periodKey ? totalValueRow?.data[periodKey] : 0) || 0;


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


const polarChartOptions = computed(() => ({
    chart: {
        type: 'polarArea',
        height: 400,
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
    },

    stroke: {

    },
    fill: {
        opacity: 0.9
    },
    legend: {
        position: 'bottom',
        offsetY: 0
    },

    // ⬇️⬇️⬇️ 1. เพิ่มส่วนนี้เพื่อซ่อนตัวเลขแกน Y ⬇️⬇️⬇️
    yaxis: {
        labels: {
            show: false
        }
    },


    dataLabels: {
        enabled: true,
        formatter: function (val: number, opts: any) {

            const totalSum = opts.w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0);


            const percentage = totalSum > 0 ? ((val / totalSum) * 100).toFixed(1) : '0.0';


            const actualValue = val * 1000000;


            const formattedValue = actualValue.toLocaleString('th-TH', { maximumFractionDigits: 0 });


            return [
                formattedValue + ' บาท',
                percentage + '%'
            ];
        },
        style: {
            fontSize: '12px',

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


                const actualValue = val * 1000000;
                return actualValue.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' บาท';
            }
        }
    },

    // ⬇️⬇️⬇️ 2. แก้ไขส่วนนี้เพื่อแสดงเส้นประสีเทา ⬇️⬇️⬇️
    plotOptions: {
        polarArea: {
            rings: {
                strokeWidth: 1,
                strokeColor: '#e0e0e0',
                strokeDashArray: 4
            },
            spokes: {
                strokeWidth: 1, // ⬅️ เปลี่ยนจาก 0
                strokeColor: '#e0e0e0', // ⬅️ สีเทา
                strokeDashArray: 4 // ⬅️ ทำให้เป็นเส้นประ
            },
        }
    }
    // ⬆️⬆️⬆️ สิ้นสุดส่วนที่ 2 ⬆️⬆️⬆️
}));

const generateMockMemberData = (): MemberSubmission[] => {
    const memberData = summaryData.value?.membership_data || [];


    if (Array.isArray(memberData)) {
        return memberData as MemberSubmission[];
    }

    console.warn('Membership data format is incorrect or empty:', memberData);
    return [];
};


const memberSubmissionSummary = computed(() => {
    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');
    const allUsersCount = users.length;

    const targetPeriods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const hasFilters = targetPeriods.length > 0 &&
        (selectyear.value.length > 0 || selectMonths.value.length > 0 || selectQuarters.value.length > 0);

    // --- START: V.6 (เพิ่มการนับแยกย่อย) ---

    // 1. ใช้ Set เพื่อนับ "ยอดรวม
    //    (Unique)"
    //    สำหรับ
    //    Donut
    //    Chart
    //    และ
    //    แถว
    //    สรุป
    let submittedInPeriod_Set = new Set<string>();

    // 2. [ใหม่]
    //    ใช้
    //    Array
    //    เพื่อนับ
    //    "ยอด
    //    แยก
    //    ย่อย"
    //    สำหรับ
    //    แถว
    //    รายละเอียด
    //    ใน
    //    ตาราง
    const periodCounts: { key: string, label: string, count: number }[] = [];

    // --- Loop 1: คำนวณ
    //    ยอด
    //    แยก
    //    ย่อย
    //    และ
    //    รวบ
    //    รวม
    //    ยอด
    //    Unique
    //    ---
    if (targetPeriods.length > 0) {
        targetPeriods.forEach(period => {
            let countForThisPeriod = 0; // นับ 0
            // ใหม่
            // ทุก
            // period

            users.forEach(user => {
                let hasSubmittedThisPeriod = false;

                // ตรวจสอบ
                //    ว่า
                //    user
                //    คน
                //    นี้
                //    ส่ง
                //    ใน
                //    period
                //    นี้
                //    หรือไม่
                if (period.monthIndex) {
                    // เคส
                    //    ราย
                    //    เดือน
                    if (user.submissions_in_period[period.year]?.includes(period.monthIndex)) {
                        hasSubmittedThisPeriod = true;
                    }
                } else if (period.year) {
                    // เคส
                    //    ราย
                    //    ปี
                    if ((user.submissions_by_year[period.year] || 0) > 0) {
                        hasSubmittedThisPeriod = true;
                    }
                }

                if (hasSubmittedThisPeriod) {
                    submittedInPeriod_Set.add(user.member_id); // เพิ่ม
                    // ใน
                    // "ยอด
                    // รวม
                    // (Unique)"
                    countForThisPeriod++; // เพิ่ม
                    // ใน
                    // "ยอด
                    // แยก
                    // ย่อย"
                }
            });

            // [ใหม่]
            //    เก็บ
            //    ผล
            //    ลัพธ์
            //    ของ
            //    "ยอด
            //    แยก
            //    ย่อย"
            //    ของ
            //    period
            //    นี้
            periodCounts.push({
                key: period.key,
                label: `สมาชิกที่กรอก ${period.label}`, // เช่น "สมาชิกที่กรอก มกราคม 2567"
                count: countForThisPeriod
            });
        });
    } else {
        // Default (ถ้า
        //    ไม่
        //    มี
        //    ฟิลเตอร์):
        //    ใช้
        //    ยอด
        //    รวม
        //    all-time
        //    สำหรับ
        //    "ยอด
        //    รวม
        //    (Unique)"
        users.forEach(user => {
            if (user.total_submitted_count > 0) {
                submittedInPeriod_Set.add(user.member_id);
            }
        });
        // (ยอด
        //    แยก
        //    ย่อย
        //    periodCounts
        //    จะ
        //    เป็น
        //    array
        //    ว่าง)
    }

    // --- คำนวณ
    //    แถว
    //    สรุป
    //    (ตรรกะ
    //    V.5
    //    เดิม)
    //    ---
    const submittedInPeriod_Count = submittedInPeriod_Set.size;
    const notSubmittedInPeriod_Count = allUsersCount - submittedInPeriod_Count;

    const submittedLabel = hasFilters ? 'สมาชิกที่กรอก (ในช่วงที่เลือก)' : 'สมาชิกที่เคยกรอก (ทั้งหมด)';
    const notSubmittedLabel = hasFilters ? 'สมาชิกที่ไม่ได้กรอก (ในช่วงที่เลือก)' : 'สมาชิกที่ไม่เคยกรอก (ทั้งหมด)';

    return {
        totalUsers: allUsersCount,
        totalUsersIncludingAdmin: allMembers.length,

        // แถว
        //    สรุป
        //    (สำหรับ
        //    Donut
        //    และ
        //    2
        //    แถว
        //    หลัก
        //    ใน
        //    ตาราง)
        submittedTotal: submittedInPeriod_Count,
        notSubmittedTotal: notSubmittedInPeriod_Count,
        submittedLabel: submittedLabel,
        notSubmittedLabel: notSubmittedLabel,
        donutData: [submittedInPeriod_Count, notSubmittedInPeriod_Count],
        donutLabels: [submittedLabel, notSubmittedLabel],

        // [ใหม่]
        //    แถว
        //    แยก
        //    ย่อย
        //    (สำหรับ
        //    v-for
        //    ใหม่
        //    ใน
        //    ตาราง)
        periodCounts: periodCounts,
    };
    // --- END: V.6 ---
});


const memberListChartData = computed(() => {

    const allMembers = generateMockMemberData();
    const users = allMembers.filter(u => u.role === 'user');

    // --- START: การเปลี่ยนแปลง
    //    V.5
    //    ---

    // 1. ดึง "ช่วงเวลา" ที่ผู้ใช้เลือก
    const targetPeriods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');

    // 2. สร้าง
    //    Set
    //    ของ
    //    "ปี"
    //    ที่
    //    เกี่ยวข้อง
    //    กับ
    //    ฟิลเตอร์
    const yearsToAggregateSet = new Set<string>();
    if (targetPeriods.length > 0) {
        targetPeriods.forEach(p => yearsToAggregateSet.add(p.year));
    }

    const yearsToAggregate = Array.from(yearsToAggregateSet);

    const aggregatedUsers = users.map(user => {
        let totalSubmissionsInPeriod = 0;

        // 3. กำหนด
        //    "ปี"
        //    ที่
        //    จะ
        //    ใช้
        //    รวม
        //    ยอด
        let yearsToSum: string[];
        if (yearsToAggregate.length > 0) {
            // ถ้า
            //    มี
            //    ฟิลเตอร์
            //    (เช่น
            //    [ '2567',
            //    '2566'
            //    ])
            //    ให้
            //    ใช้
            //    ปี
            //    นั้น
            yearsToSum = yearsToAggregate;
        } else {
            // ถ้า
            //    ไม่
            //    มี
            //    ฟิลเตอร์
            //    ให้
            //    ใช้
            //    ทุก
            //    ปี
            //    ที่
            //    มี
            //    ข้อมูล
            yearsToSum = Object.keys(user.submissions_by_year);
        }

        // 4. [สำคัญ]
        //    รวม
        //    ยอด
        //    "รายปี"
        //    (submissions_by_year)
        //    ตาม
        //    "ปี"
        //    ที่
        //    เลือก
        //    (ตรรกะ
        //    นี้
        //    ไม่
        //    สามารถ
        //    เจาะ
        //    จง
        //    ราย
        //    เดือน
        //    /ไตรมาส
        //    ได้
        //    เพราะ
        //    ข้อ
        //    จำกัด
        //    ของ
        //    data
        //    structure)
        yearsToSum.forEach(year => {
            totalSubmissionsInPeriod += (user.submissions_by_year[year] || 0);
        });

        return {
            name: user.name,
            submissions: totalSubmissionsInPeriod,
        };
    }).filter(u => u.submissions > 0); // กรอง
    //    คน
    //    ที่
    //    ไม่
    //    มี
    //    ยอด
    //    ออก

    // --- END: การเปลี่ยนแปลง
    //    V.5
    //    ---

    aggregatedUsers.sort((a, b) => b.submissions - a.submissions);

    return {
        series: [{
            name: `จำนวนสัญญาที่กรอก (รวมยอดทั้งปี)`,
            data: aggregatedUsers.map(u => u.submissions)
        }],
        categories: aggregatedUsers.map(u => u.name)
    };
});

// ก้อนที่ 1: computed สำหรับความสูง (แยกออกมา)
const barChartHeight = computed(() => {
    const len = memberListChartData.value.categories.length;
    return len > 0
        ? 350 + (len * 30)
        : 350;
});

// ก้อนที่ 2: computed สำหรับ Options (ที่ "ไม่มี" height อยู่ข้างใน)
// ก้อนที่ 2: (แก้ไข) เปลี่ยนเป็น ref
const barChartOptions = ref({
    chart: {
        type: 'bar',
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
        categories: [] as string[],
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
});

// (เพิ่ม) Watcher สำหรับอัปเดต categories และบังคับ re-render
watch(memberListChartData, (newData) => {
    // 1. อัปเดต options (เหมือนเดิม)
    barChartOptions.value = {
        ...barChartOptions.value,
        xaxis: {
            ...barChartOptions.value.xaxis,
            categories: newData.categories
        }
    };

    // 2. สั่งเปลี่ยน key เพื่อบังคับให้ Vue สร้าง chart ใหม่
    barChartKey.value += 1; // ⬅️ เพิ่มบรรทัดนี้
});
const barChartKey = ref(0);

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


interface MemberMonthlyData {
    name: string;
    submissions: Record<string, string>;
    role: 'user' | 'admin' | 'master';
    member_type: string;
    total_submitted_in_period: number;
}

const memberMonthlySubmissionTableData = computed(() => {
    // 1. ดึงข้อมูลสมาชิกและช่วงเวลาที่กำลังแสดงผล
    const members = summaryData.value?.membership_data || [];

    // 2. กรองเอาเฉพาะช่วงเวลาที่จะแสดงในคอลัมน์ตาราง (ไม่เอา 'TOTAL_PERIODS' ถ้ามี)
    const periodsToDisplay = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');

    // 3. (ทางเลือก) ถ้าไม่มีข้อมูลสมาชิก ก็คืนค่าว่างไป
    if (members.length === 0) {
        return [];
    }
    const filteredMembers = members.filter(member =>
        ['สามัญ', 'สมทบ', 'วิสามัญ ก'].includes(member.member_type)
    );



    // 5. วนลูปสมาชิกแต่ละคนเพื่อสร้างข้อมูลสำหรับตาราง
    return filteredMembers.map(member => {
        const submissions: Record<string, 'X' | '-'> = {};

        // นี่คือส่วนสำคัญ: เราจะนับผลรวมใหม่ โดยเริ่มจาก 0
        let calculatedTotalInPeriod = 0;

        // 6. วนลูปตามคอลัมน์ช่วงเวลาที่แสดง (เช่น 'ม.ค. 67', 'ก.พ. 67')
        periodsToDisplay.forEach(period => {
            // period.key จะมีค่าเช่น '2567-1' (จาก computed 'tablePeriods')
            // period.year คือ '2567', period.monthIndex คือ 1
            const year = period.year;
            const month = period.monthIndex;

            // ตรวจสอบว่าสมาชิกคนนี้ มีข้อมูลส่งของ ปี และ เดือน นี้หรือไม่
            // จากข้อมูลดิบ: member.submissions_in_period['2567']?.includes(1)
            const hasSubmission = (month && member.submissions_in_period[year]?.includes(month)) || false;

            if (hasSubmission) {
                submissions[period.key] = 'X';

                // ถ้านับว่าเป็น 'X' ให้บวก 1 เข้าไปในผลรวมใหม่
                calculatedTotalInPeriod++;
            } else {
                submissions[period.key] = '-';
            }
        });

        // 7. คืนค่า object ใหม่สำหรับแถวในตาราง
        return {
            name: member.name,
            member_type: member.member_type, // <--- ใช้ข้อมูลจาก Interface ที่แก้ไข

            // ใช้ "ผลรวม" ที่เราคำนวณใหม่
            // แทนที่ total_submitted_count ที่เป็นผลรวมทั้งหมด
            total_submitted_in_period: calculatedTotalInPeriod,

            submissions: submissions // <--- object ที่มี 'X' และ '-'
        };
    });
});





interface GrowthRateMetrics {
    MoM: number | null;
    YoY: number | null;
    QoQ: number | null;
    YTD: number | null;
}

interface GrowthRatePeriods {
    [key: string]: GrowthRateMetrics;
}

// START: *** การเปลี่ยนแปลงที่ 1 ***
// เพิ่ม total_value_raw และ total_units_raw เพื่อเก็บข้อมูลดิบ
interface GrowthRateCategory {
    categoryName: string;
    total_value: GrowthRatePeriods;
    total_units: GrowthRatePeriods;
    total_value_raw: Record<string, number>; // <--- เพิ่ม
    total_units_raw: Record<string, number>; // <--- เพิ่ม
}
// END: *** การเปลี่ยนแปลงที่ 1 ***


type MetricGrowthKey = 'total_value' | 'total_units';


const getAggregatedValue = (year: string, startMonth: number, endMonth: number, category: string, metricKey: keyof Metrics): number => {
    let sum = 0;
    const monthlyData = summaryData.value?.monthly_data[year];


    if (metricKey === 'average_price_per_sqm') return 0;

    if (!monthlyData) return 0;

    for (let month = startMonth; month <= endMonth; month++) {
        if (month < 1 || month > 12) continue;

        const metrics = monthlyData[month]?.[category];
        if (metrics) {
            sum += metrics[metricKey] || 0;
        }
    }
    return sum;
};

const getMetricValue = (period: typeof tablePeriods.value[0], category: string, metricKey: keyof Metrics): number => {
    let metrics: Metrics | undefined;

    if (period.monthIndex && period.monthIndex !== 0) {
        metrics = summaryData.value?.monthly_data[period.year]?.[period.monthIndex]?.[category];
    } else if (!period.monthIndex && period.year !== 'TOTAL') {
        metrics = summaryData.value?.yearly_data[period.year]?.[category];
    }

    return metrics ? (metrics[metricKey as keyof Metrics] || 0) : 0;
};


const growthRateReportTableData = computed<GrowthRateCategory[]>(() => {
    if (!summaryData.value || tablePeriods.value.length === 0) {
        return [];
    }


    const periodsToCalculate = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    const allCategories = [...valueCategories, 'รวม'];
    const finalTable: GrowthRateCategory[] = [];
    const metricsToTrack: MetricGrowthKey[] = ['total_value', 'total_units'];

    allCategories.forEach(categoryName => {
        // START: *** การเปลี่ยนแปลงที่ 2 ***
        // เพิ่ม total_value_raw และ total_units_raw ตอนสร้าง categoryData
        const categoryData: GrowthRateCategory = {
            categoryName: categoryName,
            total_value: {},
            total_units: {},
            total_value_raw: {}, // <--- เพิ่ม
            total_units_raw: {}  // <--- เพิ่ม
        };
        // END: *** การเปลี่ยนแปลงที่ 2 ***

        metricsToTrack.forEach(metricKey => {
            periodsToCalculate.forEach((currentPeriod, index) => {
                const currentValue = getMetricValue(currentPeriod, categoryName, metricKey as keyof Metrics);
                const periodKey = currentPeriod.key;

                // START: *** การเปลี่ยนแปลงที่ 3 ***
                // เก็บค่า currentValue (ข้อมูลดิบ) ลงใน _raw
                categoryData[`${metricKey}_raw`][periodKey] = currentValue;
                // END: *** การเปลี่ยนแปลงที่ 3 ***

                let MoM: number | null = null;
                let YoY: number | null = null;
                let QoQ: number | null = null;
                let YTD: number | null = null;


                if (!currentPeriod.monthIndex && currentPeriod.year !== 'TOTAL') {
                    const prevYear = (parseInt(currentPeriod.year) - 1).toString();
                    const prevPeriod: typeof periodsToCalculate[0] = { key: `Y${prevYear}`, label: `สรุปปี ${prevYear}`, year: prevYear };
                    const prevValue = getMetricValue(prevPeriod, categoryName, metricKey as keyof Metrics);

                    if (prevValue !== 0) {
                        YoY = ((currentValue / prevValue) - 1) * 100;
                    } else if (currentValue > 0) {
                        YoY = 100; // ⬅️ ถ้าของเก่าเป็น 0 แต่ของใหม่มีค่า ให้แสดง 100%
                    }
                }

                if (currentPeriod.monthIndex) {
                    const currentYear = currentPeriod.year;
                    const currentMonth = currentPeriod.monthIndex;


                    if (index > 0) {
                        const prevPeriod = periodsToCalculate[index - 1];
                        const isPreviousMonth = (prevPeriod.monthIndex === currentMonth - 1) && (prevPeriod.year === currentYear);
                        const isJanFromDec = (currentMonth === 1) && (prevPeriod.monthIndex === 12) && (parseInt(prevPeriod.year) === parseInt(currentYear) - 1);

                        if (isPreviousMonth || isJanFromDec) {
                            const prevMonthValue = getMetricValue(prevPeriod, categoryName, metricKey as keyof Metrics);
                            if (prevMonthValue !== 0) {
                                MoM = ((currentValue / prevMonthValue) - 1) * 100;
                            }
                        }
                    }


                    const prevYear = (parseInt(currentYear) - 1).toString();
                    const prevYearPeriod: typeof periodsToCalculate[0] = {
                        key: `M${currentMonth}Y${prevYear}`, label: `${Months[currentMonth - 1]} ${prevYear}`,
                        year: prevYear, monthIndex: currentMonth
                    };
                    const prevYearValue = getMetricValue(prevYearPeriod, categoryName, metricKey as keyof Metrics);

                    if (prevYearValue !== 0) {
                        YoY = ((currentValue / prevYearValue) - 1) * 100;
                    } else if (currentValue > 0) {
                        YoY = 100; // ⬅️ ถ้าของเก่าเป็น 0 แต่ของใหม่มีค่า ให้แสดง 100%
                    }


                    const currentYTDValue = getAggregatedValue(currentYear, 1, currentMonth, categoryName, metricKey as keyof Metrics);
                    const prevYTDValue = getAggregatedValue(prevYear, 1, currentMonth, categoryName, metricKey as keyof Metrics);

                    if (prevYTDValue !== 0) {
                        YTD = ((currentYTDValue / prevYTDValue) - 1) * 100;
                    } else if (currentYTDValue > 0) {
                        YTD = 100; // ⬅️ ถ้าของเก่าเป็น 0 แต่ของใหม่มีค่า ให้แสดง 100%
                    }


                    const currentQuarter = Quarters.find(q => q.months.includes(currentMonth));

                    if (currentQuarter && currentQuarter.months[currentQuarter.months.length - 1] === currentMonth) {
                        const currentQuarterIndex = currentQuarter.index;

                        let prevQYear = currentYear;
                        let prevQIndex: number;

                        if (currentQuarterIndex > 1) {
                            prevQIndex = currentQuarterIndex - 1;
                        } else {
                            prevQIndex = 4;
                            prevQYear = prevYear;
                        }

                        const prevQuarter = Quarters.find(q => q.index === prevQIndex);

                        if (prevQuarter) {

                            const currentQValue = getAggregatedValue(currentYear, currentQuarter.months[0], currentQuarter.months[currentQuarter.months.length - 1], categoryName, metricKey as keyof Metrics);


                            const prevQValue = getAggregatedValue(prevQYear, prevQuarter.months[0], prevQuarter.months[prevQuarter.months.length - 1], categoryName, metricKey as keyof Metrics);

                            if (prevQValue !== 0) {
                                QoQ = ((currentQValue / prevQValue) - 1) * 100;
                            } else if (currentQValue > 0) {
                                QoQ = 100; // ⬅️ ถ้าของเก่าเป็น 0 แต่ของใหม่มีค่า ให้แสดง 100%
                            }
                        }
                    }
                }


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

const lastPeriod = computed(() => {
    const periods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');
    return periods.length > 0 ? periods[periods.length - 1] : null;
});

// ... ต่อจาก computed อื่นๆ

// ⬇️⬇️⬇️ เพิ่ม computed นี้ ⬇️⬇️⬇️
const monthlySubmissionBarChartData = computed(() => {
    // 1. ดึงข้อมูลสมาชิกทั้งหมด (เช่น 50 คน)
    const totalUsers = memberSubmissionSummary.value.totalUsers;

    // 2. ดึงข้อมูลการกรอกแบบรายเดือน (เช่น [{ label: '... ม.ค. 67', count: 10 }, ...])
    const periodCounts = memberSubmissionSummary.value.periodCounts;

    if (periodCounts.length === 0 || totalUsers === 0) {
        // ถ้าไม่มีข้อมูล ให้คืนค่าว่าง
        return { series: [], categories: [] };
    }

    // 3. สร้างแกน X (Categories)
    //    (ตัดคำว่า "สมาชิกที่กรอก " ออก)
    const categories = periodCounts.map(p => p.label.replace('สมาชิกที่กรอก ', ''));

    // 4. สร้าง Series "กรอก"
    const seriesDataSubmitted = periodCounts.map(p => p.count);

    // 5. สร้าง Series "ไม่กรอก" (โดยเอา ทั้งหมด - กรอก)
    const seriesDataNotSubmitted = periodCounts.map(p => totalUsers - p.count);

    return {
        series: [
            { name: 'สมาชิกที่กรอก', data: seriesDataSubmitted },
            { name: 'สมาชิกที่ยังไม่กรอก', data: seriesDataNotSubmitted }
        ],
        categories: categories
    };
});

// ... ต่อจาก const donutChartOptions ...

// ⬇️⬇️⬇️ เพิ่ม 3 ส่วนนี้ (key, options, watch) ⬇️⬇️⬇️

// 1. Key สำหรับบังคับ re-render
const monthlyBarChartKey = ref(0);

// 2. Options สำหรับกราฟแท่ง (แบบ Stacked)
const monthlyBarChartOptions = ref({
    chart: {
        type: 'bar',
        height: 350,
        stacked: true, // ⬅️ ทำให้เป็นกราฟแท่งแบบซ้อนกัน
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: true, tools: { download: true } },
    },
    plotOptions: {
        bar: {
            horizontal: false, // ⬅️ เป็นแนวตั้ง
            columnWidth: '60%',
        },
    },
    dataLabels: {
        enabled: true, // ⬅️ แสดงตัวเลขบนแท่ง
        formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 })
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    xaxis: {
        categories: [] as string[], // ⬅️ แกน X (จะถูกเติมโดย watch)
        title: {
            text: 'ช่วงเวลาที่เลือก'
        }
    },
    yaxis: {
        title: {
            text: 'จำนวนสมาชิก (คน)'
        }
    },
    fill: {
        opacity: 1
    },
    tooltip: {
        y: {
            formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' คน'
        }
    },
    legend: {
        position: 'top', // ⬅️ คำอธิบาย (กรอก/ไม่กรอก) ไว้ด้านบน
        horizontalAlign: 'center'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
});

watch(monthlySubmissionBarChartData, (newData) => {
    // (แก้ไข) อัปเดต options โดยการ mutate property โดยตรง
    monthlyBarChartOptions.value.xaxis.categories = newData.categories;

    monthlyBarChartKey.value += 1;
});


// ... ต่อจาก computed ของกราฟแท่งรายเดือน (monthlySubmissionBarChartData) ...

// ⬇️⬇️⬇️ เริ่ม: กราฟแท่งใหม่ (แยกตามประเภทสมาชิก) ⬇️⬇️⬇️
const memberTypeSubmissionChartData = computed(() => {
    // 1. กำหนดประเภทสมาชิกเป้าหมาย
    const typesToTrack = ['สามัญ', 'สมทบ', 'วิสามัญ ก'];

    // 2. ดึงข้อมูลสมาชิกทั้งหมด
    const allMembers = summaryData.value?.membership_data || [];

    // 3. ดึง "คอลัมน์" ที่กำลังแสดงผล (เช่น 'ม.ค. 67', 'ก.พ. 67')
    const targetPeriods = tablePeriods.value.filter(p => p.key !== 'TOTAL_PERIODS');

    // 4. ถ้าไม่มีการเลือกฟิลเตอร์ (ไม่มีคอลัมน์) ก็ไม่ต้องแสดงกราฟ
    if (targetPeriods.length === 0 || allMembers.length === 0) {
        return { series: [], categories: typesToTrack };
    }

    const submittedSeriesData: number[] = [];
    const notSubmittedSeriesData: number[] = [];

    // 5. วนลูปตามประเภทสมาชิกเป้าหมาย
    typesToTrack.forEach(type => {

        // 5a. หาสมาชิกทั้งหมดที่อยู่ในประเภทนี้
        const membersInType = allMembers.filter(m => m.member_type === type);
        const totalInType = membersInType.length;

        // 5b. นับจำนวนคน (Unique) ที่ "กรอก" ในช่วงที่เลือก
        const submittedMembersInThisType = new Set<string>();

        membersInType.forEach(member => {
            // ตรวจสอบว่าสมาชิกคนนี้ มี 'X' (กรอก) ในคอลัมน์ใดคอลัมน์หนึ่งที่แสดงหรือไม่
            const hasSubmissionInPeriod = targetPeriods.some(period => {
                const year = period.year;
                const month = period.monthIndex;
                // ตรวจสอบข้อมูลดิบ (submissions_in_period)
                return (month && member.submissions_in_period[year]?.includes(month)) || false;
            });

            if (hasSubmissionInPeriod) {
                submittedMembersInThisType.add(member.member_id);
            }
        });

        const submittedCount = submittedMembersInThisType.size;

        // 5c. คำนวณยอดและเก็บข้อมูล
        submittedSeriesData.push(submittedCount);
        notSubmittedSeriesData.push(totalInType - submittedCount);
    });

    // 6. คืนค่าข้อมูลสำหรับ ApexCharts (แบบ stacked)
    return {
        series: [
            { name: 'สมาชิกที่กรอก (ในช่วงที่เลือก)', data: submittedSeriesData },
            { name: 'สมาชิกที่ยังไม่กรอก (ในช่วงที่เลือก)', data: notSubmittedSeriesData }
        ],
        categories: typesToTrack // แกน X
    };
});


// ... ต่อจาก watch(monthlySubmissionBarChartData, ...)

// ⬇️⬇️⬇️ เริ่ม: Options/Key/Watch สำหรับกราฟประเภทสมาชิก ⬇️⬇️⬇️

// 1. Key
const memberTypeBarChartKey = ref(0);

// 2. Options (คัดลอกจาก monthlyBarChartOptions แต่ปรับแกน X)
const memberTypeBarChartOptions = ref({
    chart: {
        type: 'bar',
        height: 350,
        stacked: true, // ⬅️ กราฟแบบซ้อนกัน
        fontFamily: 'inherit',
        foreColor: '#adb0bb',
        toolbar: { show: true, tools: { download: true } },
    },
    plotOptions: {
        bar: {
            horizontal: false, // ⬅️ กราฟแนวตั้ง
            columnWidth: '60%',
        },
    },
    dataLabels: {
        enabled: true,
        formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 })
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    xaxis: {
        // ⬅️ เปลี่ยน categories และ title
        categories: ['สามัญ', 'สมทบ', 'วิสามัญ ก'] as string[],
        title: {
            text: 'ประเภทสมาชิก'
        }
    },
    yaxis: {
        title: {
            text: 'จำนวนสมาชิก (คน)'
        }
    },
    fill: {
        opacity: 1
    },
    tooltip: {
        y: {
            formatter: (val: number) => val.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' คน'
        }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'center'
    },
    grid: {
        show: true,
        strokeDashArray: 4,
        borderColor: 'rgba(0, 0, 0, 0.1)'
    },
});

// 3. Watcher (เพื่อสั่ง re-render เมื่อข้อมูล series เปลี่ยน)
watch(memberTypeSubmissionChartData, (newData) => {
    // อัปเดต Categories (เผื่อไว้ แต่ปกติค่านี้จะคงที่)
    memberTypeBarChartOptions.value.xaxis.categories = newData.categories;
    // สั่ง re-render
    memberTypeBarChartKey.value += 1;
});

// ⬆️⬆️⬆️ สิ้นสุด: Options/Key/Watch ⬆️⬆️⬆️

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
                                            - <b>ไม่เลือกปี/เดือน/ไตรมาส:</b>
                                            แสดงข้อมูลของปีปัจจุบันถึงเดือนปัจจุบัน<br />
                                            - <b>เทียบเดือน/ไตรมาส (ในปีเดียวกัน):</b> เลือก 1 ปี
                                            และเลือกหลายเดือน/ไตรมาส<br />
                                            - <b>เทียบปี (ในเดือน/ไตรมาสเดียวกัน):</b> เลือกหลายปี และเลือก 1
                                            เดือน/ไตรมาส
                                        </v-alert>

                                        <v-row>
                                            <v-col cols="12" md="4">
                                                <v-combobox v-model="selectyear" :items="year" label="เลือกปี" chips
                                                    multiple clearable variant="outlined"
                                                    density="comfortable"></v-combobox>
                                            </v-col>
                                            <v-col cols="12" md="4">
                                                <v-combobox v-model="selectQuarters" :items="Quarters.map(q => q.name)"
                                                    label="เลือกไตรมาส" chips multiple clearable variant="outlined"
                                                    density="comfortable"></v-combobox>
                                            </v-col>
                                            <v-col cols="12" md="4">
                                                <v-combobox v-model="selectMonths" :items="availableMonths"
                                                    label="เลือกเดือน" chips multiple clearable variant="outlined"
                                                    density="comfortable"></v-combobox>
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
                            <h3 class="card-title mb-1">ตารางอัตราการเติบโต (แยกตามมูลค่าบ้าน)
                            </h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>

                            <v-table density="compact" class="mt-4 border">
                                <thead>
                                    <tr>
                                        <th rowspan="3" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">ช่วงมูลค่าบ้าน</th>
                                        <th rowspan="3" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">รายละเอียด</th>
                                        <th :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-h6 border-b-sm border-e">
                                            ข้อมูล
                                        </th>
                                        <th :colspan="showQoQ ? 4 : 3" class="text-center text-h6 border-b-sm">
                                            อัตราการเติบโต (สรุป)
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="(period, index) in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                            :key="`raw-${period.key}`" class="text-center text-subtitle-1 border-b-sm"
                                            style="min-width: 120px;" :class="{
                                                'bg-blue-grey-lighten-5': !period.monthIndex,
                                                'border-e': index === tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length - 1
                                            }">
                                            {{ period.label }}
                                        </th>

                                        <th :colspan="showQoQ ? 4 : 3"
                                            class="text-center text-h6 border-b-sm bg-blue-grey-lighten-5"
                                            v-if="lastPeriod">
                                            สรุป ณ {{ lastPeriod.label }}
                                        </th>
                                        <th v-else :colspan="showQoQ ? 4 : 3" class="text-center text-h6 border-b-sm">
                                            -
                                        </th>
                                    </tr>
                                    <tr>
                                        <td :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="border-e"></td>

                                        <template v-if="lastPeriod">
                                            <th class="text-center text-subtitle-1" style="min-width: 80px;">MoM%</th>
                                            <th class="text-center text-subtitle-1" style="min-width: 80px;">YoY%</th>
                                            <th v-if="showQoQ" class="text-center text-subtitle-1"
                                                style="min-width: 80px;">QoQ%</th>
                                            <th class="text-center text-subtitle-1 border-e" style="min-width: 80px;">
                                                YTD%</th>
                                        </template>
                                        <template v-else>
                                            <td :colspan="showQoQ ? 4 : 3"></td>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="growthRateReportTableData.length > 0 && lastPeriod">
                                        <template v-for="(category, catIndex) in growthRateReportTableData"
                                            :key="category.categoryName">

                                            <template v-for="(metricEntry, rowIndex) in [
                                                { key: 'total_units', name: 'จำนวนหลัง', format: metricRows.find(r => r.key === 'total_units')!.format, data: category.total_units, raw_data: category.total_units_raw },
                                                { key: 'total_value', name: 'มูลค่ารวม (บาท)', format: metricRows.find(r => r.key === 'total_value')!.format, data: category.total_value, raw_data: category.total_value_raw }
                                            ]" :key="`${category.categoryName}-${metricEntry.key}`">

                                                <tr :class="{
                                                    'bg-blue-grey-lighten-5': category.categoryName === 'รวม',
                                                    'border-t': rowIndex === 0,
                                                }">
                                                    <td v-if="rowIndex === 0" :rowspan="2"
                                                        class="text-left font-weight-bold text-subtitle-2 border-e"
                                                        :class="{ 'text-primary': category.categoryName === 'รวม' }">
                                                        {{ category.categoryName }}
                                                    </td>

                                                    <td class="text-left text-caption border-e">
                                                        {{ metricEntry.name }}
                                                    </td>

                                                    <template
                                                        v-for="(period, index) in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                                        :key="`raw-cell-${period.key}`">
                                                        <td class="text-right text-subtitle-2" :class="{
                                                            'border-e': index === tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length - 1,
                                                            'text-primary font-weight-bold': category.categoryName === 'รวม' && metricEntry.key === 'total_value'
                                                        }">
                                                            {{ metricEntry.format(metricEntry.raw_data[period.key] || 0)
                                                            }}
                                                        </td>
                                                    </template>

                                                    <template v-if="lastPeriod">
                                                        <td class="text-right text-subtitle-2"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.MoM || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.MoM || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.MoM != null
                                                                ? metricEntry.data[lastPeriod.key]!.MoM!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>

                                                        <td class="text-right text-subtitle-2"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.YoY || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.YoY || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.YoY != null
                                                                ? metricEntry.data[lastPeriod.key]!.YoY!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>

                                                        <td v-if="showQoQ" class="text-right text-subtitle-2"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.QoQ || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.QoQ || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.QoQ != null
                                                                ? metricEntry.data[lastPeriod.key]!.QoQ!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>

                                                        <td class="text-right text-subtitle-2 border-e"
                                                            :class="{ 'text-success': (metricEntry.data[lastPeriod.key]?.YTD || 0) > 0, 'text-error': (metricEntry.data[lastPeriod.key]?.YTD || 0) < 0 }">
                                                            {{ metricEntry.data[lastPeriod.key]?.YTD != null
                                                                ? metricEntry.data[lastPeriod.key]!.YTD!.toFixed(2) + '%'
                                                                : '-'
                                                            }}
                                                        </td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="2 + tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length + (showQoQ ? 4 : 3)"
                                            class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก
                                            หรือช่วงเวลาที่เลือกไม่ต่อเนื่อง
                                        </td>
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
                            <apexchart id="chart1" type="line" :options="chartOptions" :series="chartSeries"
                                height="350" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟสัดส่วนมูลค่ารวม แยกตามมูลค่าบ้าน</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart id="polarChartPrice" type="polarArea"
                                :options="{ ...polarChartOptions, labels: polarChartPriceData.labels }"
                                :series="polarChartPriceData.series" height="400" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">กราฟสัดส่วนมูลค่ารวม แยกตามภูมิภาค</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>
                            <apexchart id="polarChartRegion" type="polarArea"
                                :options="{ ...polarChartOptions, labels: polarChartRegionData.labels }"
                                :series="polarChartRegionData.series" height="400" />
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>
                            <h3 class="card-title mb-1">รายงานสถานะการกรอกสัญญาของสมาชิก</h3>
                            <h5 class="card-subtitle">ข้อมูลอ้างอิง: สมาชิกประเภท User ทั้งหมด</h5>
                            <v-row class="mt-4">
                                <v-col cols="12" md="6">
                                    <div>
                                        <v-card-title
                                            class="text-center text-subtitle-1 pt-4 pb-0">สถานะการกรอกสัญญารวม</v-card-title>
                                        <v-card-text class="pa-2">
                                            <apexchart id="donutChartMember" type="donut" :options="donutChartOptions"
                                                :series="memberSubmissionSummary.donutData" height="350" />
                                        </v-card-text>
                                    </div>
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-table density="compact" class="mt-4 border">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="text-center text-subtitle-1">สรุปจำนวนสมาชิก</th>
                                            </tr>
                                        </thead>
                                        <tbody>


                                            <!-- <tr class="bg-blue-grey-lighten-5">
                                                <td class="font-weight-bold">สมาชิกทั้งหมด (รวมผู้ดูแล)</td>
                                                <td class="text-right font-weight-bold">{{
                                                    memberSubmissionSummary.totalUsersIncludingAdmin }} คน</td>
                                            </tr> -->
                                            <tr>
                                                <td class="font-weight-bold">สมาชิกทั้งหมด</td>
                                                <td class="text-right font-weight-bold">{{
                                                    memberSubmissionSummary.totalUsers }} คน</td>
                                            </tr>


                                            <template v-if="memberSubmissionSummary.periodCounts.length > 0">
                                                <tr class="bg-blue-grey-lighten-5">

                                                </tr>
                                                <tr v-for="period in memberSubmissionSummary.periodCounts"
                                                    :key="period.key">
                                                    <td>{{ period.label }}</td>
                                                    <td class="text-right">{{ period.count }} คน</td>
                                                </tr>
                                            </template>
                                            <tr>
                                                <td>{{ memberSubmissionSummary.submittedLabel }}</td>
                                                <td class="text-right">{{ memberSubmissionSummary.submittedTotal }} คน
                                                </td>
                                            </tr>
                                            <tr class="bg-red-lighten-5">
                                                <td class="font-weight-bold text-error">{{
                                                    memberSubmissionSummary.notSubmittedLabel }}
                                                </td>
                                                <td class="text-right font-weight-bold text-error">{{
                                                    memberSubmissionSummary.notSubmittedTotal }} คน</td>
                                            </tr>


                                        </tbody>
                                    </v-table>
                                </v-col>

                                <v-col cols="12" v-if="memberSubmissionSummary.periodCounts.length > 0">
                                    <v-divider class="my-4"></v-divider>
                                    <v-card-title class="text-center text-subtitle-1 pt-4 pb-0">
                                        สถานะการกรอกสัญญา (จำแนกตามช่วงเวลาที่เลือก)
                                    </v-card-title>
                                    <v-card-text class="pa-2">
                                        <apexchart id="monthlyBarChartMember" type="bar" :key="monthlyBarChartKey"
                                            :options="monthlyBarChartOptions"
                                            :series="monthlySubmissionBarChartData.series" height="350" />
                                    </v-card-text>
                                </v-col>
                                <!-- <v-col cols="12" md="12">

                                    <v-card-title
                                        class="text-center text-subtitle-1 pt-4 pb-0">จำนวนสัญญาที่กรอกต่อรายสมาชิก
                                        (ปีที่เลือก)</v-card-title>
                                    <v-card-text class="pa-2">
                                        <apexchart id="barChartMember" type="bar" :key="barChartKey"
                                            :options="barChartOptions" :series="memberListChartData.series"
                                            :height="barChartHeight" />
                                    </v-card-text>

                                </v-col> -->
                            </v-row>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12">
                    <v-card>
                        <v-card-text>


                            <v-col cols="12">
                                <v-card-title class="text-center text-subtitle-1 pt-4 pb-0">
                                    สถานะการกรอกสัญญา (จำแนกตามประเภทสมาชิก)
                                </v-card-title>
                                <v-card-text class="pa-2">
                                    <apexchart id="memberTypeBarChart" type="bar" :key="memberTypeBarChartKey"
                                        :options="memberTypeBarChartOptions"
                                        :series="memberTypeSubmissionChartData.series" height="350" class="mt-4"
                                        v-if="memberTypeSubmissionChartData.series.length > 0" />

                                    <v-alert v-else type="info" variant="tonal" density="compact" class="mt-4">
                                        ไม่พบข้อมูลสำหรับกราฟนี้ (กรุณาเลือกช่วงเวลาเพื่อแสดงผล)
                                    </v-alert>
                                </v-card-text>
                            </v-col>

                            <v-divider class="my-4"></v-divider>
                            <br><br></br>
                            <h3 class="card-title mb-1">ตารางสถานะการกรอกสัญญาต่อเดือน (แยกตามรายสมาชิก)</h3>
                            <h5 class="card-subtitle">{{ chartSubtitle }}</h5>



                            <v-table density="compact" class="mt-4  border">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 200px;">
                                            รายชื่อสมาชิก</th>

                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 100px;">
                                            ประเภท </th>

                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 100px;">
                                            ยอดรวม </th>

                                        <th :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                            :key="period.key" class="text-center text-subtitle-1"
                                            style="min-width: 80px;">
                                            {{ period.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="memberMonthlySubmissionTableData.length > 0">
                                        <tr v-for="member in memberMonthlySubmissionTableData" :key="member.name">
                                            <td class="text-left font-weight-bold text-caption border-e">{{ member.name
                                            }}</td>

                                            <td class="text-left text-caption border-e">
                                                {{ member.member_type }} </td>
                                            <td class="text-center font-weight-bold text-subtitle-2 border-e">
                                                {{ member.total_submitted_in_period.toLocaleString('th-TH') }}
                                            </td>
                                            <td v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                                :key="period.key" class="text-center text-subtitle-2"
                                                :class="{ 'text-success font-weight-bold': member.submissions[period.key] === 'X', 'text-error': member.submissions[period.key] === '-' }">
                                                {{ member.submissions[period.key] }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="3 + tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลสมาชิกที่แสดงผลตามเงื่อนไข
                                        </td>
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
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            ช่วงมูลค่าบ้าน</th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            รายละเอียด</th>
                                        <th :colspan="tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS').length"
                                            class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>

                                        <th v-if="lastPeriod && lastPeriod.monthIndex" :colspan="2"
                                            class="text-center text-h6 border-b-sm bg-blue-grey-lighten-5">
                                            อัตราเติบโต (ณ {{ lastPeriod.label }})
                                        </th>

                                        <th v-if="tablePeriods.some(p => p.key === 'TOTAL_PERIODS')" rowspan="2"
                                            class="text-right text-subtitle-1 border-e text-primary"
                                            style="min-width: 120px;">
                                            รวมทุกช่วง
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                            :key="period.key" class="text-right text-subtitle-1 border-e"
                                            style="min-width: 120px;">
                                            {{ period.label }}
                                        </th>

                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1 border-e" style="min-width: 80px;">MoM%
                                        </th>
                                        <th v-if="lastPeriod && lastPeriod.monthIndex"
                                            class="text-center text-subtitle-1 border-e" style="min-width: 80px;">YTD%
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="monthlyReportTableData.length > 0">
                                        <template v-for="(category, catIndex) in monthlyReportTableData"
                                            :key="category.categoryName">
                                            <tr v-for="(row, rowIndex) in category.rows"
                                                :key="`${category.categoryName}-${row.metricKey}`" :class="{
                                                    'bg-blue-grey-lighten-5': category.categoryName === 'รวม',
                                                    'border-t': rowIndex === 0,
                                                }">
                                                <td v-if="rowIndex === 0" :rowspan="category.rows.length"
                                                    class="text-left font-weight-bold text-subtitle-2 border-e"
                                                    :class="{ 'text-primary': category.categoryName === 'รวม' }">
                                                    {{ category.categoryName }}
                                                </td>
                                                <td class="text-left text-caption border-e">{{ row.metricName }}</td>

                                                <td v-for="period in tablePeriods.filter(p => p.key !== 'TOTAL_PERIODS')"
                                                    :key="period.key" class="text-right text-subtitle-2 border-e">
                                                    {{ row.format(row.data[period.key] || 0) }}
                                                </td>

                                                <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                    class="text-right text-subtitle-2 border-e"
                                                    :class="{ 'text-success': (row.growth.mom || 0) > 0, 'text-error': (row.growth.mom || 0) < 0 }">
                                                    {{ row.growth.mom != null ? row.growth.mom.toFixed(2) + '%' : '-' }}
                                                </td>
                                                <td v-if="lastPeriod && lastPeriod.monthIndex"
                                                    class="text-right text-subtitle-2 border-e"
                                                    :class="{ 'text-success': (row.growth.ytd || 0) > 0, 'text-error': (row.growth.ytd || 0) < 0 }">
                                                    {{ row.growth.ytd != null ? row.growth.ytd.toFixed(2) + '%' : '-' }}
                                                </td>

                                                <td v-if="tablePeriods.some(p => p.key === 'TOTAL_PERIODS')"
                                                    class="text-right text-subtitle-2 border-e"
                                                    :class="{ 'text-primary font-weight-bold': category.categoryName === 'รวม' && row.metricKey === 'total_value' }">
                                                    {{ row.format(row.data['TOTAL_PERIODS'] || 0) }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 2 + (lastPeriod && lastPeriod.monthIndex ? 2 : 0)"
                                            class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก
                                        </td>
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
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">ภูมิภาค
                                        </th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            รายละเอียด</th>
                                        <th :colspan="tablePeriods.length" class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="period in tablePeriods" :key="period.key"
                                            class="text-right text-subtitle-1"
                                            :class="{ 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key, 'text-primary': period.key === 'TOTAL_PERIODS' }"
                                            style="min-width: 120px;">
                                            {{ period.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="regionReportTableData.length > 0">
                                        <template v-for="(region, regIndex) in regionReportTableData"
                                            :key="region.categoryName">
                                            <tr v-for="(row, rowIndex) in region.rows"
                                                :key="`${region.categoryName}-${row.metricKey}`" :class="{
                                                    'bg-blue-grey-lighten-5': region.categoryName === 'รวมทั่วประเทศ',
                                                    'border-t': rowIndex === 0,
                                                }">
                                                <td v-if="rowIndex === 0" :rowspan="region.rows.length"
                                                    class="text-left font-weight-bold text-subtitle-2 border-e"
                                                    :class="{ 'text-primary': region.categoryName === 'รวมทั่วประเทศ' }">
                                                    {{ region.categoryName }}
                                                </td>

                                                <td class="text-left text-caption border-e">{{ row.metricName }}</td>

                                                <td v-for="period in tablePeriods" :key="period.key"
                                                    class="text-right text-subtitle-2"
                                                    :class="{ 'text-primary font-weight-bold': region.categoryName === 'รวมทั่วประเทศ' && row.metricKey === 'total_value', 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key }">
                                                    {{ row.format(row.data[period.key] || 0) }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 2" class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
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
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">ภูมิภาค
                                        </th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            ช่วงมูลค่าบ้าน</th>
                                        <th rowspan="2" class="text-center text-subtitle-1 border-e"
                                            style="min-width: 150px;">
                                            รายละเอียด</th>
                                        <th :colspan="tablePeriods.length" class="text-center text-h6 border-b-sm">
                                            <span v-if="tablePeriods.length > 0">{{ chartSubtitle }}</span>
                                            <span v-else>ไม่พบช่วงเวลาที่เลือก</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="period in tablePeriods" :key="period.key"
                                            class="text-right text-subtitle-1"
                                            :class="{ 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key, 'text-primary': period.key === 'TOTAL_PERIODS' }"
                                            style="min-width: 120px;">
                                            {{ period.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="regionAndCategoryReportTableData.length > 0">
                                        <template v-for="(regionGroup, regIndex) in regionAndCategoryReportTableData"
                                            :key="regionGroup.regionName">
                                            <template v-for="(category, catIndex) in regionGroup.categories"
                                                :key="`${regionGroup.regionName}-${category.categoryName}`">
                                                <tr v-for="(row, rowIndex) in category.rows"
                                                    :key="`${regionGroup.regionName}-${category.categoryName}-${row.metricKey}`"
                                                    :class="{

                                                        'bg-blue-grey-lighten-5': regionGroup.regionName === 'รวมทั่วประเทศ' || category.categoryName === 'รวม',
                                                        'border-t': rowIndex === 0 && catIndex === 0,
                                                        'border-t-sm': rowIndex === 0 && category.categoryName === 'รวม',
                                                    }">
                                                    <td v-if="rowIndex === 0 && catIndex === 0"
                                                        :rowspan="regionGroup.categories.length * category.rows.length"
                                                        class="text-left font-weight-bold text-subtitle-2 border-e"
                                                        :class="{ 'text-primary': regionGroup.regionName === 'รวมทั่วประเทศ' }">
                                                        {{ regionGroup.regionName }}
                                                    </td>

                                                    <td v-if="rowIndex === 0" :rowspan="category.rows.length"
                                                        class="text-left font-weight-bold text-caption border-e"
                                                        :class="{ 'text-primary': category.categoryName === 'รวม' }">
                                                        {{ category.categoryName }}
                                                    </td>

                                                    <td class="text-left text-caption border-e">{{ row.metricName }}
                                                    </td>



                                                    <td v-for="period in tablePeriods" :key="period.key"
                                                        class="text-right text-subtitle-2"
                                                        :class="{ 'text-primary font-weight-bold': (regionGroup.regionName === 'รวมทั่วประเทศ' || category.categoryName === 'รวม') && row.metricKey === 'total_value', 'border-e': period.key !== tablePeriods[tablePeriods.length - 1].key }">
                                                        {{ row.format(row.data[period.key] || 0) }}
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td :colspan="tablePeriods.length + 3" class="text-center text-subtitle-1 py-4">
                                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
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
