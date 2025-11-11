<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import ExcelJS from 'exceljs';
import type { BorderStyle, Cell } from 'exceljs';
import jsPDF from 'jspdf'; // ⭐️ [ใหม่] Import PDF
import html2canvas from 'html2canvas'; // ⭐️ [ใหม่] Import Canvas

// --- (1) ส่วนฟิลเตอร์และเวลา (เหมือน house.vue) ---
const jsDate = new Date();
const currentJsYear = jsDate.getFullYear();
const currentJsMonth = jsDate.getMonth() + 1; // (1-12)

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

// ⭐️ [ใหม่] State สำหรับ Loading และ Snackbar (จาก house.vue)
const exportLoading = ref(false);
const showSnackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('info');
const snackbarTimeout = ref(5000);

// --- (2) โครงสร้างข้อมูล ---

// (2.1) โครงสร้างข้อมูล Metrics
interface RegionMetrics {
    unit: number;
    value: number;
    area: number;
    price_per_sqm: number;
}
// ⭐️ data[Region][PriceRange]
const rawData = ref<Record<string, Record<string, { unit: number; value: number; area: number }>>>({});


// ⭐️ [ใหม่] เพิ่ม State นี้ (เหมือน house.vue)
const previousYearDetailedTableData = ref<Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>>>({});

// ⭐️ detailedTableData[Month][Region][PriceRange]
const detailedTableData = ref<Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>>>({});


// (2.2) ค่าคงที่ (แกนแถว)
const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
const dataTypes: (keyof RegionMetrics)[] = ['unit', 'value', 'area', 'price_per_sqm'];
const dataTypeLabels: Record<string, string> = {
    unit: 'จำนวนหลัง',
    value: 'มูลค่ารวม',
    area: 'พื้นที่ใช้สอย',
    price_per_sqm: 'ราคาเฉลี่ย/ตร.ม.'
};

// (2.3) แกนคอลัมน์ (ดึงข้อมูลแบบ Dynamic)
const regions = computed(() => Object.keys(rawData.value).sort());

// --- (3) State ของฟิลเตอร์ (เหมือน house.vue) ---
const allMonthItems = [
    { title: 'มกราคม', short: 'ม.ค.', value: 1 },
    { title: 'กุมภาพันธ์', short: 'ก.พ.', value: 2 },
    { title: 'มีนาคม', short: 'มี.ค.', value: 3 },
    { title: 'เมษายน', short: 'เม.ย.', value: 4 },
    { title: 'พฤษภาคม', short: 'พ.ค.', value: 5 },
    { title: 'มิถุนายน', short: 'มิ.ย.', value: 6 },
    { title: 'กรกฎาคม', short: 'ก.ค.', value: 7 },
    { title: 'สิงหาคม', short: 'ส.ค.', value: 8 },
    { title: 'กันยายน', short: 'ก.ย.', value: 9 },
    { title: 'ตุลาคม', short: 'ต.ค.', value: 10 },
    { title: 'พฤศจิกายน', short: 'พ.ย.', value: 11 },
    { title: 'ธันวาคม', short: 'ธ.ค.', value: 12 }
];

const selectedYear = ref(currentJsYear + 543);
const isUpdatingFromMonths = ref(false);
const selectedQuarter = ref('all');
const selectedMonths = ref<number[]>([]);
const yearOptions = ref(Array.from({ length: 2 }, (_, i) => currentJsYear + 543 - i));

const activeTab = ref('summary'); // (State นี้ไม่ได้ใช้แล้ว แต่เก็บไว้ก่อนได้)

const monthOptions = computed(() => {
    const yearAD = selectedYear.value - 543;
    if (yearAD === currentJsYear) {
        return allMonthItems.filter((m) => m.value <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        return [];
    } else {
        return allMonthItems;
    }
});

const quarterOptions = ref([
    { title: 'ทุกไตรมาส / ทุกเดือน', value: 'all' },
    { title: 'ไตรมาส 1 (ม.ค. - มี.ค.)', value: 'Q1' },
    { title: 'ไตรมาส 2 (เม.ย. - มิ.ย.)', value: 'Q2' },
    { title: 'ไตรมาส 3 (ก.ค. - ก.ย.)', value: 'Q3' },
    { title: 'ไตรมาส 4 (ต.ค. - ธ.ค.)', value: 'Q4' }
]);

// --- (4) ฟังก์ชันดึงข้อมูล (ใช้ API จาก house.vue) ---

function processMonthData(data: any): Record<string, Record<string, { unit: number; value: number; area: number }>> {
    // data for one month: { "Region1": { "PriceRange1": ... }, ... }
    // สำหรับ region.vue ข้อมูลดิบพร้อมใช้งาน ไม่ต้องประมวลผลเพิ่ม
    return data || {};
}
// (4.1) ดึงข้อมูล "สรุป" (สำหรับ Chart, Cards, และ Pivot Table)
const fetchData = async () => {
    if (!userId && userRole !== 'admin') return;
    if (selectedMonths.value.length === 0 || !selectedYear.value) {
        rawData.value = {};
        return;
    }
    try {
        const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                user_id: userId,
                buddhist_year: selectedYear.value,
                months: selectedMonths.value.sort((a, b) => a - b),
                role: userRole
            })
        });
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        const data = await res.json();
        rawData.value = data;
        console.log('✅ ข้อมูลดิบ (สรุป) ที่ได้รับ:', data);

    } catch (err) {
        console.error('❌ Error fetching summary data:', err);
        rawData.value = {};
    }
};

// (4.2) fetchDetailedTableData
const fetchDetailedTableData = async () => {
    if (!userId && userRole !== 'admin') return;

    let monthsToFetch = [...selectedMonths.value].sort((a, b) => a - b);

    if (monthsToFetch.length === 0 || !selectedYear.value) {
        detailedTableData.value = {};
        previousYearDetailedTableData.value = {}; // ⭐️ [ใหม่] ล้างข้อมูลปีก่อน
        return;
    }

    // ⭐️ [จาก house.vue] ตรวจสอบและเพิ่ม "เดือนก่อนหน้า" 1 เดือนสำหรับคำนวณ MoM
    const firstMonth = monthsToFetch[0];
    if (firstMonth > 1) {
        const prevMonth = firstMonth - 1;
        if (!monthsToFetch.includes(prevMonth)) {
            monthsToFetch.push(prevMonth); // เพิ่มเดือนก่อนหน้าเข้าไปใน list ที่จะ fetch
        }
    }

    const currentYear = selectedYear.value;
    const previousYear = currentYear - 1;

    // ⭐️ [จาก house.vue] สร้างฟังก์ชันย่อยสำหรับ Fetch API
    const fetchMonthData = async (year: number, month: number) => {
        try {
            const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    user_id: userId,
                    buddhist_year: year,
                    months: [month],
                    role: userRole
                })
            });
            if (!res.ok) {
                console.error(`Error fetching data for month ${month}/${year}: Status ${res.status}`);
                return null; // คืนค่า null ถ้า error
            }
            return await res.json();
        } catch (err) {
            console.error(`❌ Error fetching detailed summary for month ${month}/${year}:`, err);
            return null; // คืนค่า null ถ้า error
        }
    };

    // ⭐️ [จาก house.vue] สร้างรายการ Promises ทั้งหมด
    const promises: Promise<{ month: number; year: number; data: any; }>[] = [];

    // ⭐️ [ปรับปรุง] เราต้องดึง "ทุกเดือน" ที่จำเป็นสำหรับ MoM และ YTD
    // `monthsToFetch` (มีเดือนก่อนหน้า)
    // `selectedMonths.value` (มีแค่เดือนที่เลือก)
    const allMonthsSet = new Set([...monthsToFetch, ...selectedMonths.value]);

    for (const month of allMonthsSet) {
        // ข้อมูลปีปัจจุบัน
        promises.push(fetchMonthData(currentYear, month).then(data => ({ month, year: currentYear, data })));
        // ข้อมูลปีก่อน (สำหรับ YTD)
        promises.push(fetchMonthData(previousYear, month).then(data => ({ month, year: previousYear, data })));
    }

    // ⭐️ [จาก house.vue] สั่ง Run Promise ทั้งหมด
    const results = await Promise.all(promises);

    const newCurrentYearData: Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>> = {};
    const newPreviousYearData: Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>> = {};

    // ⭐️ [จาก house.vue] ประมวลผลข้อมูล
    for (const result of results) {
        if (result.year === currentYear && result.data) {
            newCurrentYearData[result.month] = processMonthData(result.data);
        } else if (result.year === previousYear && result.data) {
            newPreviousYearData[result.month] = processMonthData(result.data);
        }
    }

    detailedTableData.value = newCurrentYearData;
    previousYearDetailedTableData.value = newPreviousYearData; // ⭐️ [ใหม่] เก็บข้อมูลปีก่อน

    console.log('✅ (ปรับปรุง) ข้อมูลตาราง (ปัจจุบัน):', newCurrentYearData);
    console.log('✅ (ใหม่) ข้อมูลตาราง (ปีก่อน):', newPreviousYearData);
};

function getRawDataMonthly(
    dataSource: Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>>,
    monthValue: number,
    region: string,
    range: string,
    metric: 'unit' | 'value' | 'area'
): number {
    return Number(dataSource?.[monthValue]?.[region]?.[range]?.[metric] || 0);
}

// ⭐️ [ใหม่] Helper (ปรับปรุงจาก house.vue) เพื่อคำนวณ YTD% (YoY)
function getSummaryGrandTotalYoY(metric: keyof RegionMetrics): number | null {

    const currentValue = getMonthlyTabGrandTotal(metric);

    let previousValue = 0;
    let previousTotalValue = 0;
    let previousTotalArea = 0;

    for (const month of displayedMonths.value) {
        for (const region of regions.value) {
            for (const range of priceRanges) {
                if (metric === 'price_per_sqm') {
                    previousTotalValue += getRawDataMonthly(previousYearDetailedTableData.value, month.value, region, range, 'value');
                    previousTotalArea += getRawDataMonthly(previousYearDetailedTableData.value, month.value, region, range, 'area');
                }
                // ⭐️ [แก้ไข] ⭐️
                // เปลี่ยนจาก 'else if (metric !== 'price_per_sqm')' เป็น 'else'
                else {
                    // TS รู้ว่า 'metric' ในที่นี้คือ 'unit' | 'value' | 'area'
                    previousValue += getRawDataMonthly(previousYearDetailedTableData.value, month.value, region, range, metric);
                }
            }
        }
    }

    if (metric === 'price_per_sqm') {
        const previousAvg = previousTotalArea > 0 ? (previousTotalValue / previousTotalArea) : 0;
        return calculateMoMPercent(currentValue, previousAvg);
    }

    return calculateMoMPercent(currentValue, previousValue);
}

// ⭐️ [แทนที่] (7.3) เปลี่ยนจาก MoM เป็น YTD% (YoY)
const summaryCardYTDData = computed(() => {

    // ⭐️ ตรวจสอบว่ามีข้อมูลปีก่อนหรือไม่
    if (Object.keys(previousYearDetailedTableData.value).length === 0) {
        return { unit: null, value: null, area: null, price_per_sqm: null };
    }

    const ytdValues: Record<string, string | null> = {};

    for (const field of dataTypes) {
        // ⭐️ เรียกใช้ Helper ใหม่
        const percent = getSummaryGrandTotalYoY(field);

        if (percent === null) {
            ytdValues[field] = null;
        } else {
            const percentStr = percent.toFixed(0); // ไม่เอาทศนิยม
            if (percent > 0) {
                ytdValues[field] = `(+${percentStr}%)`;
            } else if (percent < 0) {
                ytdValues[field] = `(${percentStr}%)`;
            } else {
                ytdValues[field] = null; // ไม่แสดง 0%
            }
        }
    }

    return ytdValues as Record<keyof RegionMetrics, string | null>;
});

// --- (5) ⭐️ [ใหม่] State และ Logic สำหรับฟิลเตอร์ปุ่ม (จาก house.vue) ---
const showMoM = ref(true); // ⭐️ State สำหรับ v-switch
const selectedTimeRange = ref<string | null>(null); // State สำหรับ 1M, 2M, 3M, 6M, YTD
const timeRangeOptions = [
    { label: '1M', value: '1M' },
    { label: '2M', value: '2M' },
    { label: '3M', value: '3M' },
    { label: '6M', value: '6M' },
    { label: 'YTD', value: 'YTD' }
];

// Helper (จาก house.vue)
function getAvailableMonths(yearInBE: number): number[] {
    const yearAD = yearInBE - 543;
    const allMonths = allMonthItems.map(m => m.value);
    if (yearAD === currentJsYear) {
        return allMonths.filter(m => m <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        return [];
    } else {
        return allMonths;
    }
}

// Helper (จาก house.vue)
function setTimeRange(range: string | null) {
    if (!range) {
        return;
    }
    const availableMonths = getAvailableMonths(selectedYear.value);
    if (availableMonths.length === 0) {
        selectedMonths.value = [];
        return;
    }

    let newSelectedMonths: number[] = [];
    switch (range) {
        case '1M': newSelectedMonths = availableMonths.slice(-1); break;
        case '2M': newSelectedMonths = availableMonths.slice(-2); break;
        case '3M': newSelectedMonths = availableMonths.slice(-3); break;
        case '6M': newSelectedMonths = availableMonths.slice(-6); break;
        case 'YTD': newSelectedMonths = [...availableMonths]; break;
    }

    selectedMonths.value = newSelectedMonths;
}

// ⭐️ [ใหม่] Watcher (จาก house.vue)
watch(selectedTimeRange, (newRange) => {
    if (newRange) {
        setTimeRange(newRange); // เรียกใช้ setTimeRange เพื่ออัปเดต `selectedMonths`
    }
});


// --- Watchers (ปรับปรุง) ---
watch(selectedQuarter, (newQuarter) => {
    if (isUpdatingFromMonths.value) {
        isUpdatingFromMonths.value = false;
        return;
    }
    const validMonthValues = monthOptions.value.map(m => m.value);
    const filterValidMonths = (months: number[]) => months.filter(m => validMonthValues.includes(m));

    if (newQuarter === 'all') updateToAllMonths();
    else if (newQuarter === 'Q1') selectedMonths.value = filterValidMonths([1, 2, 3]);
    else if (newQuarter === 'Q2') selectedMonths.value = filterValidMonths([4, 5, 6]);
    else if (newQuarter === 'Q3') selectedMonths.value = filterValidMonths([7, 8, 9]);
    else if (newQuarter === 'Q4') selectedMonths.value = filterValidMonths([10, 11, 12]);
});

// ⭐️ [ปรับปรุง] watch(selectedYear) (ผสาน house.vue)
watch(selectedYear, () => {
    if (selectedTimeRange.value) {
        setTimeRange(selectedTimeRange.value);
    } else {
        const validMonths = monthOptions.value.map((m) => m.value);
        selectedMonths.value = selectedMonths.value.filter((m) => validMonths.includes(m));

        if (selectedQuarter.value === 'all') {
            updateToAllMonths();
        } else {
            const currentQuarter = selectedQuarter.value;
            selectedQuarter.value = '';
            selectedQuarter.value = currentQuarter;
        }
    }
});

// ⭐️ [ปรับปรุง] watch(selectedMonths) (ผสาน house.vue)
watch(
    selectedMonths,
    (newMonths, oldMonths) => {
        if (newMonths.join(',') !== oldMonths.join(',')) {
            activeTab.value = 'summary';
        }

        const sortedMonthsKey = [...newMonths].sort((a, b) => a - b).join(',');

        const validMonthValues = monthOptions.value.map(m => m.value);
        const q1Months = [1, 2, 3].filter(m => validMonthValues.includes(m)).join(',');
        const q2Months = [4, 5, 6].filter(m => validMonthValues.includes(m)).join(',');
        const q3Months = [7, 8, 9].filter(m => validMonthValues.includes(m)).join(',');
        const q4Months = [10, 11, 12].filter(m => validMonthValues.includes(m)).join(',');

        if (sortedMonthsKey === q1Months && q1Months.length > 0) selectedQuarter.value = 'Q1';
        else if (sortedMonthsKey === q2Months && q2Months.length > 0) selectedQuarter.value = 'Q2';
        else if (sortedMonthsKey === q3Months && q3Months.length > 0) selectedQuarter.value = 'Q3';
        else if (sortedMonthsKey === q4Months && q4Months.length > 0) selectedQuarter.value = 'Q4';
        else {
            const allMonthsCurrentYear = allMonthItems.map((m) => m.value).slice(0, currentJsMonth).join(',');
            const allMonthsPastYear = allMonthItems.map((m) => m.value).join(',');

            if (sortedMonthsKey === allMonthsCurrentYear || sortedMonthsKey === allMonthsPastYear) {
                if (selectedQuarter.value !== 'all') {
                    isUpdatingFromMonths.value = true;
                    selectedQuarter.value = 'all';
                }
            } else if (selectedQuarter.value !== 'all') {
                isUpdatingFromMonths.value = true;
                selectedQuarter.value = 'all';
            }
        }

        const availableMonths = getAvailableMonths(selectedYear.value);
        let matchedRange: string | null = null;
        if (availableMonths.length > 0) {
            if (sortedMonthsKey === availableMonths.slice(-1).join(',')) matchedRange = '1M';
            else if (sortedMonthsKey === availableMonths.slice(-2).join(',')) matchedRange = '2M';
            else if (sortedMonthsKey === availableMonths.slice(-3).join(',')) matchedRange = '3M';
            else if (sortedMonthsKey === availableMonths.slice(-6).join(',')) matchedRange = '6M';
            else if (sortedMonthsKey === availableMonths.join(',')) matchedRange = 'YTD';
        }
        selectedTimeRange.value = matchedRange;

        fetchData();
        fetchDetailedTableData();
    },
    { deep: true }
);

const updateToAllMonths = () => {
    const yearAD = selectedYear.value - 543;
    if (yearAD === currentJsYear) {
        selectedMonths.value = allMonthItems.map((m) => m.value).filter((m) => m <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        selectedMonths.value = [];
    } else {
        selectedMonths.value = allMonthItems.map((m) => m.value);
    }
};

onMounted(() => {
    updateToAllMonths();
});

// ... (
// ⭐️ START: All Helper functions (6.A, 6.B, 6.D)
// ... (Tons of helper functions go here)
// ... )

// (6.B.7)
function getRegionHorizontalTotal(region: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const month of displayedMonths.value) {
        totalUnit += getMonthlyVerticalTotal(month.value, region, 'unit');
        totalValue += getMonthlyVerticalTotal(month.value, region, 'value');
        totalArea += getMonthlyVerticalTotal(month.value, region, 'area');
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.B.8)
function getMonthlyTabGrandTotal(field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const month of displayedMonths.value) {
        totalUnit += getMonthlyGrandTotal(month.value, 'unit');
        totalValue += getMonthlyGrandTotal(month.value, 'value');
        totalArea += getMonthlyGrandTotal(month.value, 'area');
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.A.1)
function getSummaryNumericValue(region: string, range: string, field: 'unit' | 'value' | 'area'): number {
    return Number(rawData.value?.[region]?.[range]?.[field] || 0);
}

// (6.A.2)
function getSummaryCalculatedMetrics(region: string, range: string): RegionMetrics {
    const unit = getSummaryNumericValue(region, range, 'unit');
    const value = getSummaryNumericValue(region, range, 'value');
    const area = getSummaryNumericValue(region, range, 'area');
    const price_per_sqm = area > 0 ? (value / area) : 0;
    return { unit, value, area, price_per_sqm };
}

// (6.A.3)
function getSummaryHorizontalTotal(range: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const region of regions.value) {
        const metrics = getSummaryCalculatedMetrics(region, range);
        totalUnit += metrics.unit;
        totalValue += metrics.value;
        totalArea += metrics.area;
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.A.4)
function getSummaryVerticalTotal(region: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const range of priceRanges) {
        const metrics = rawData.value?.[region]?.[range];
        if (metrics) {
            totalUnit += Number(metrics.unit) || 0;
            totalValue += Number(metrics.value) || 0;
            totalArea += Number(metrics.area) || 0;
        }
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.A.5)
function getSummaryGrandTotal(field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const region of regions.value) {
        for (const range of priceRanges) {
            const metrics = rawData.value?.[region]?.[range];
            if (metrics) {
                totalUnit += Number(metrics.unit) || 0;
                totalValue += Number(metrics.value) || 0;
                totalArea += Number(metrics.area) || 0;
            }
        }
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.B.1)
const displayedMonths = computed(() => {
    return allMonthItems.filter(m => selectedMonths.value.includes(m.value))
        .sort((a, b) => a.value - b.value);
});

// (6.B.2)
function getMonthlyNumericValue(month: number, region: string, range: string, field: 'unit' | 'value' | 'area'): number {
    return Number(detailedTableData.value?.[month]?.[region]?.[range]?.[field] || 0);
}

// (6.B.3)
function getMonthlyCalculatedMetrics(month: number, region: string, range: string): RegionMetrics {
    const unit = getMonthlyNumericValue(month, region, range, 'unit');
    const value = getMonthlyNumericValue(month, region, range, 'value');
    const area = getMonthlyNumericValue(month, region, range, 'area');
    const price_per_sqm = area > 0 ? (value / area) : 0;
    return { unit, value, area, price_per_sqm };
}

// (6.B.4)
function getMonthlyHorizontalTotal(month: number, range: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const region of regions.value) {
        const metrics = getMonthlyCalculatedMetrics(month, region, range);
        totalUnit += metrics.unit;
        totalValue += metrics.value;
        totalArea += metrics.area;
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.B.5)
function getMonthlyVerticalTotal(month: number, region: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const range of priceRanges) {
        const metrics = getMonthlyCalculatedMetrics(month, region, range);
        totalUnit += metrics.unit;
        totalValue += metrics.value;
        totalArea += metrics.area;
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.B.6)
function getMonthlyGrandTotal(month: number, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;
    for (const region of regions.value) {
        for (const range of priceRanges) {
            const metrics = getMonthlyCalculatedMetrics(month, region, range);
            totalUnit += metrics.unit;
            totalValue += metrics.value;
            totalArea += metrics.area;
        }
    }
    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// (6.C)
function formatValue(value: number, field: keyof RegionMetrics): string {
    if (field === 'unit') {
        return value.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
    return value.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// (6.D.1)
function calculateMoMPercent(current: number, prior: number): number | null {
    if (prior === 0) {
        return current > 0 ? 100 : 0;
    }
    const percent = ((current - prior) / prior) * 100;
    if (!isFinite(percent)) {
        return null;
    }
    return percent;
}

// (6.D.2)
function getMoMFormatted(month: number, region: string, range: string, field: keyof RegionMetrics): string {
    const priorMonth = month - 1;
    if (!detailedTableData.value[priorMonth]) {
        return "";
    }
    const currentValue = getMonthlyCalculatedMetrics(month, region, range)[field];
    const priorValue = getMonthlyCalculatedMetrics(priorMonth, region, range)[field];
    const percent = calculateMoMPercent(currentValue, priorValue);
    if (percent === null) return "";
    const percentStr = percent.toFixed(0);
    if (percent > 0) return `<span class="mom-percent text-success">(+${percentStr}%)</span>`;
    if (percent < 0) return `<span class="mom-percent text-error">(${percentStr}%)</span>`;
    return "";
}

// (6.D.3)
function getMoMFormatted_HorizontalTotal(month: number, range: string, field: keyof RegionMetrics): string {
    const priorMonth = month - 1;
    if (!detailedTableData.value[priorMonth]) return "";
    const currentValue = getMonthlyHorizontalTotal(month, range, field);
    const priorValue = getMonthlyHorizontalTotal(priorMonth, range, field);
    const percent = calculateMoMPercent(currentValue, priorValue);
    if (percent === null) return "";
    const percentStr = percent.toFixed(0);
    if (percent > 0) return `<span class="mom-percent text-success">(+${percentStr}%)</span>`;
    if (percent < 0) return `<span class="mom-percent text-error">(${percentStr}%)</span>`;
    return "";
}

// (6.D.4)
function getMoMFormatted_VerticalTotal(month: number, region: string, field: keyof RegionMetrics): string {
    const priorMonth = month - 1;
    if (!detailedTableData.value[priorMonth]) return "";
    const currentValue = getMonthlyVerticalTotal(month, region, field);
    const priorValue = getMonthlyVerticalTotal(priorMonth, region, field);
    const percent = calculateMoMPercent(currentValue, priorValue);
    if (percent === null) return "";
    const percentStr = percent.toFixed(0);
    if (percent > 0) return `<span class="mom-percent text-success">(+${percentStr}%)</span>`;
    if (percent < 0) return `<span class="mom-percent text-error">(${percentStr}%)</span>`;
    return "";
}

// (6.D.5)
function getMoMFormatted_GrandTotal(month: number, field: keyof RegionMetrics): string {
    const priorMonth = month - 1;
    if (!detailedTableData.value[priorMonth]) return "";
    const currentValue = getMonthlyGrandTotal(month, field);
    const priorValue = getMonthlyGrandTotal(priorMonth, field);
    const percent = calculateMoMPercent(currentValue, priorValue);
    if (percent === null) return "";
    const percentStr = percent.toFixed(0);
    if (percent > 0) return `<span class="mom-percent text-success">(+${percentStr}%)</span>`;
    if (percent < 0) return `<span class="mom-percent text-error">(${percentStr}%)</span>`;
    return "";
}

// ... ⭐️ END: Helper functions ⭐️ ...


// --- (7) Computed Data สำหรับ Graph และ Cards ---

// (7.A) State และฟังก์ชันสำหรับ Highlight (เหมือน house.vue)
const selectedHighlight = ref<keyof RegionMetrics | null>(null);

function highlightRow(field: keyof RegionMetrics) {
    if (selectedHighlight.value === field) {
        selectedHighlight.value = null;
    } else {
        selectedHighlight.value = field;
    }
}

function isRowVisible(field: keyof RegionMetrics): boolean {
    if (selectedHighlight.value === null) {
        return true;
    }
    return selectedHighlight.value === field;
}

function getHighlightStyle(field: keyof RegionMetrics) {
    if (selectedHighlight.value !== field) return null;
    if (field === 'unit') return { backgroundColor: '#E3F2FD' };
    if (field === 'value') return { backgroundColor: '#EDE7F6' };
    if (field === 'area') return { backgroundColor: '#FFEBEE' };
    if (field === 'price_per_sqm') return { backgroundColor: '#FFF8E1' };
    return null;
}
// (7.B) จบส่วน Highlight


// (7.1) [ปรับปรุง] ข้อมูลสำหรับ Polar Area Chart
const polarAreaSeries = computed(() => {
    const metricKey = selectedHighlight.value || 'value';
    const series = regions.value.map(region => getSummaryVerticalTotal(region, metricKey));
    const totalSum = series.reduce((a, b) => a + b, 0);
    return totalSum > 0 ? series : [];
});

// ... (อยู่ก่อนหน้า dataLabels)
// (7.1) [ปรับปรุง] Options สำหรับ Polar Area Chart
const polarAreaOptions = computed(() => {
    // ⭐️ (1) กำหนด Metric, Suffix, Title ตามค่าที่เลือก
    const metricKey = selectedHighlight.value || 'value';
    let tooltipSuffix = ' บาท';
    let dataLabelTitle = 'มูลค่ารวม';

    if (metricKey === 'unit') {
        tooltipSuffix = ' หลัง';
        dataLabelTitle = 'จำนวนหลัง';
    } else if (metricKey === 'area') {
        tooltipSuffix = ' ตร.ม.';
        dataLabelTitle = 'พื้นที่ใช้สอย';
    } else if (metricKey === 'price_per_sqm') {
        tooltipSuffix = ' บาท/ตร.ม.';
        dataLabelTitle = 'ราคาเฉลี่ย/ตร.ม.';
    }

    // ⭐️ [จุดแก้ไขที่ 1] ⭐️
    // สร้าง Array สีที่คุณต้องการ (ใส่สีได้ตามจำนวนภูมิภาค)
    const chartColors = [
        '#3F51B5', // สีน้ำเงินเข้ม
        '#03A9F4', // สีฟ้า
        '#4CAF50', // สีเขียว
        '#FFC107', // สีเหลือง
        '#F44336', // สีแดง
        '#9C27B0', // สีม่วง
        '#009688', // สีเขียวน้ำทะเล
        '#E91E63'  // สีชมพู
    ];

    // ⭐️ (2) คืนค่า Options Object
    return {
        chart: { type: 'polarArea', fontFamily: 'inherit', foreColor: '#6c757d' },
        labels: regions.value,
        legend: { position: 'bottom', horizontalAlign: 'center' },

        // ⭐️ [จุดแก้ไขที่ 2] ⭐️
        // เพิ่ม 'colors' array เข้าไปที่นี่
        colors: chartColors,

        stroke: { colors: ['#fff'] },
        fill: { opacity: 0.8 }, // ⭐️ [ปรับปรุง] ที่นี่จะกลายเป็นความทึบของสี ไม่ใช่ตัวสี
        responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }],

        tooltip: {
            theme: 'dark',
            y: {
                formatter: (val: number) => val.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + tooltipSuffix
            }
        },

        // (โค้ด dataLabels... ที่เราแก้ไขล่าสุด)
        // ... (อยู่ภายใน polarAreaOptions)
        dataLabels: {
            enabled: true,

            formatter: (val: number, opts: any) => {

                console.log(`--- DataLabel ---`);
                console.log(`val (percent):`, val);

                // 1. คำนวณ % (ยังไม่ใส่วงเล็บ)
                let percentageText = '0.00%';
                if (!isNaN(val)) percentageText = (Number(val) || 0).toFixed(2) + '%';

                const index = opts.seriesIndex;
                const regionKey = opts.w.globals.labels[index];
                const metricKey = selectedHighlight.value || 'value';

                console.log(`metricKey:`, metricKey);
                console.log(`regionKey (from labels[${index}]):`, regionKey);

                if (!regionKey) {
                    console.warn("ไม่สามารถหา regionKey ได้, index:", index);
                    return percentageText;
                }

                const rawValue = getSummaryVerticalTotal(regionKey, metricKey);
                console.log(`rawValue (total):`, rawValue);

                // 2. จัดรูปแบบ "ค่าจริง" (แบบไม่ใส่วงเล็บ)
                const rawValueText = (Number(rawValue) || 0).toLocaleString('th-TH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // ⭐️ [จุดแก้ไข] ⭐️
                // สลับลำดับ Array ให้ "ค่าจริง" (rawValueText) ขึ้นก่อน
                // และใส่ (วงเล็บ) ให้กับ "เปอร์เซ็นต์" (percentageText)
                const result = [rawValueText, `(${percentageText})`];

                console.log(`Result Array:`, result);
                console.log(`-----------------------------`);

                return result; // คืนค่า Array [ค่าจริง, (เปอร์เซ็นต์)]
            },

            // (Style และ DropShadow เหมือนเดิม)
            style: {
                fontSize: '10px'
            },
            dropShadow: {
                enabled: false
            }
        },
        // ...
        noData: { text: 'ไม่พบข้อมูลสำหรับช่วงที่เลือก', align: 'center', verticalAlign: 'middle', offsetY: 0, style: { color: '#6c757d', fontSize: '14px', fontFamily: 'inherit' } },
    }
});
// (7.C) Computed สำหรับหัวข้อกราฟ (เหมือน house.vue)
const chartTitle = computed(() => {
    const selectedKey = selectedHighlight.value;
    if (selectedKey === 'unit') return 'สัดส่วนจำนวนหลัง (Unit) ตามภูมิภาค';
    if (selectedKey === 'area') return 'สัดส่วนพื้นที่ใช้สอย (Usable Area) ตามภูมิภาค';
    if (selectedKey === 'price_per_sqm') return 'สัดส่วนราคาเฉลี่ย/ตร.ม. (Price/Sqm) ตามภูมิภาค';
    return 'สัดส่วนมูลค่ารวม (Total Value) ตามภูมิภาค';
});


// (7.2) ข้อมูลการ์ดสรุป
const summaryCardData = computed(() => {
    // ⭐️ [หมายเหตุ] ส่วนนี้ถูกแก้ไขใน v3.2 ให้คำนวณตาม tab ที่เลือก
    // แต่ใน v3.1 (โค้ดนี้) มันจะแสดง "ยอดรวมของทุกเดือนที่เลือก" เสมอ
    // ซึ่งอาจจะดีกว่าสำหรับ UI นี้ (เดี๋ยวรอดู v3.2)

    // (ตรรกะเดิมจาก v3.1)
    // if (activeTab.value !== 'summary' && typeof activeTab.value === 'number') {
    //     const currentMonth = activeTab.value;
    //     return { ... };
    // }
    return {
        unit: getSummaryGrandTotal('unit'),
        value: getSummaryGrandTotal('value'),
        area: getSummaryGrandTotal('area'),
        price_per_sqm: getSummaryGrandTotal('price_per_sqm')
    };
});

// (7.3) Computed สำหรับ MoM% ของ Summary Cards
const summaryCardMoMData = computed(() => {
    // ⭐️ [หมายเหตุ] โค้ดนี้จะคำนวณ MoM% ของการ์ด
    // โดยอิงจาก "เดือนสุดท้ายที่เลือก" เทียบกับ "เดือนก่อนหน้า"
    // (นี่คือการคาดเดา Requirement ที่ดีที่สุด)
    // (ถ้า activeTab ถูกนำกลับมาใช้, logic นี้ต้องเปลี่ยน)

    if (selectedMonths.value.length === 0) {
        return { unit: null, value: null, area: null, price_per_sqm: null };
    }

    // ⭐️ [ใหม่] หาเดือนสุดท้ายที่เลือก
    const currentMonth = Math.max(...selectedMonths.value);
    const priorMonth = currentMonth - 1;

    if (priorMonth <= 0 || !detailedTableData.value[priorMonth]) {
        return { unit: null, value: null, area: null, price_per_sqm: null };
    }

    const momValues: Record<string, string | null> = {};

    for (const field of dataTypes) {
        const currentValue = getMonthlyGrandTotal(currentMonth, field); // ⭐️ ยอดรวมของ "เดือนสุดท้าย"
        const priorValue = getMonthlyGrandTotal(priorMonth, field);   // ⭐️ ยอดรวมของ "เดือนก่อนหน้า"
        const percent = calculateMoMPercent(currentValue, priorValue);

        if (percent === null) {
            momValues[field] = null;
        } else {
            const percentStr = percent.toFixed(0);
            if (percent > 0) {
                momValues[field] = `(+${percentStr}%)`;
            } else if (percent < 0) {
                momValues[field] = `(${percentStr}%)`;
            } else {
                momValues[field] = null;
            }
        }
    }

    return momValues as Record<keyof RegionMetrics, string | null>;
});


// (7.D) Helpers สำหรับ Chart MoM% (จาก house.vue)
function getMonthTotalMoM(monthValue: number, metric: keyof RegionMetrics): number | null {
    const prevMonthValue = monthValue - 1;
    if (monthValue === 1 || !detailedTableData.value[prevMonthValue]) {
        return null;
    }
    const currentValue = getMonthlyGrandTotal(monthValue, metric);
    const previousValue = getMonthlyGrandTotal(prevMonthValue, metric);
    return calculateMoMPercent(currentValue, previousValue);
}

// (7.E) Computed สำหรับ Chart MoM% (จาก house.vue)
const chartMoMData = computed(() => {
    if (selectedMonths.value.length === 0) {
        return { percent: null, label: '', hasPreviousMonth: false };
    }
    const lastSelectedMonth = Math.max(...selectedMonths.value);
    const prevMonthValue = lastSelectedMonth - 1;
    if (lastSelectedMonth === 1 || !detailedTableData.value[prevMonthValue]) {
        const label = lastSelectedMonth === 1 ? '(ม.ค. ไม่มีข้อมูล MoM)' : '';
        return { percent: null, label: label, hasPreviousMonth: false };
    }
    const metricKey = selectedHighlight.value || 'value';
    const percent = getMonthTotalMoM(lastSelectedMonth, metricKey);
    const prevMonthShort = allMonthItems.find(m => m.value === prevMonthValue)?.short || '';
    const label = `MoM % (เทียบ ${prevMonthShort}.)`;
    return { percent: percent, label: label, hasPreviousMonth: true };
});

// (7.F) Helpers จัดรูปแบบ % (จาก house.vue)
function formatPercentage(value: number | null): string {
    if (value === null) return '';
    if (value === 0) return '';
    const prefix = value > 0 ? '+' : '';
    return `(${prefix}${value.toFixed(1)}%)`;
};
function getPercentageColor(value: number | null): string {
    if (value === null) return 'text-grey';
    if (value > 0) return 'text-success';
    if (value < 0) return 'text-error';
    return 'text-grey';
};


// ⭐️ [ใหม่] (8.A) `dynamicFilename` (จาก house.vue)
const dynamicFilename = computed(() => {
    const cleanedSubtitle = filterSubtitle.value
        .replace(/[\(\)]/g, '') // ลบวงเล็บ
        .replace(/\s+/g, '_')   // แทนที่ช่องว่างด้วย _
        .replace(/__/g, '_')
        .replace(/_-_/g, '_');

    // ⭐️ [ปรับปรุง] เปลี่ยนชื่อไฟล์
    const baseName = `รายงานสรุปภูมิภาค${cleanedSubtitle || ''}`;

    return baseName.replace(/[/\\?%*:|"<>]/g, '');
});

// ⭐️ [ใหม่] (8.B) `blobToDataURL` (จาก house.vue)
// (Helper นี้สำหรับ PDF แต่ควรมีไว้)
const blobToDataURL = (blob: Blob): Promise<string> => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            resolve(reader.result as string);
        };
        reader.onerror = (error) => reject(error);
        reader.readAsDataURL(blob);
    });
};


// ⭐️ [ปรับปรุง] (8.C) `exportToExcel` (แก้ไขใหม่ทั้งหมดให้ตรงกับตาราง HTML)
const exportToExcel = async () => {
    exportLoading.value = true;
    showSnackbar.value = false;

    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet('สรุปรายเดือน (ภูมิภาค x เดือน)');
        const fontName = 'TH Sarabun PSK'; // ⭐️ (Optional) ถ้าต้องการฟอนต์ไทย

        // --- 1. Title Row ---
        const title = `ตารางสรุปยอดรายเดือน (ภูมิภาค x เดือน) ${filterSubtitle.value}`;
        const titleRow = worksheet.addRow([title]);
        titleRow.font = { name: fontName, bold: true, size: 16 };
        // ⭐️ คำนวณจำนวนคอลัมน์ใหม่ = 1 (Label) + จำนวนเดือน + 1 (Total)
        worksheet.mergeCells(titleRow.number, 1, titleRow.number, displayedMonths.value.length + 2);

        // --- 2. Header Row ---
        const headerRowData: string[] = ['(ภูมิภาค / Metric)'];
        displayedMonths.value.forEach(month => headerRowData.push(month.short));
        headerRowData.push('รวม');

        const headerRow = worksheet.addRow(headerRowData);
        headerRow.font = { name: fontName, bold: true, size: 12 };
        headerRow.alignment = { horizontal: 'center' };
        headerRow.eachCell((cell: Cell) => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'D3D3D3' } };
            cell.border = { top: { style: 'thin' as BorderStyle }, left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle } };
        });

        // Helper function สำหรับกำหนด format ตัวเลข
        const getNumFmt = (field: string) => {
            return (field === 'unit') ? '#,##0' : '#,##0.00';
        }

        // ⭐️ [ใหม่] Helper สำหรับดึงค่า MoM% (ตัวเลข)
        const getMoM_VerticalTotal = (month: number, region: string, field: keyof RegionMetrics): number | null => {
            const priorMonth = month - 1;
            if (month === 1 || !detailedTableData.value[priorMonth]) return null;
            const currentValue = getMonthlyVerticalTotal(month, region, field);
            const priorValue = getMonthlyVerticalTotal(priorMonth, region, field);
            return calculateMoMPercent(currentValue, priorValue);
        }

        // --- 3. Data Rows (วนตาม Region) ---
        regions.value.forEach((region) => {
            // 3.1: แถวชื่อภูมิภาค
            const regionTitleRow = worksheet.addRow([region]);
            regionTitleRow.font = { name: fontName, bold: true, size: 12, color: { argb: 'FF725AF2' } };
            regionTitleRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
            worksheet.mergeCells(regionTitleRow.number, 1, regionTitleRow.number, headerRowData.length);

            // 3.2: แถวข้อมูลย่อย (unit, value, area, price_per_sqm)
            dataTypes.forEach((field) => {
                const metricLabel = dataTypeLabels[field];
                const rowData: (string | number)[] = [`  ${metricLabel}`];
                const numFmt = getNumFmt(field);
                const fieldKey = field as keyof RegionMetrics;

                // เพิ่มข้อมูลรายเดือน
                displayedMonths.value.forEach(month => {
                    rowData.push(getMonthlyVerticalTotal(month.value, region, fieldKey));
                });

                // เพิ่มข้อมูล "ผลรวมแนวนอน" (คอลัมน์ รวม)
                rowData.push(getRegionHorizontalTotal(region, fieldKey));

                const dataRow = worksheet.addRow(rowData);
                dataRow.font = { name: fontName, size: 11 };

                // กำหนดสไตล์และ Format
                dataRow.getCell(1).alignment = { horizontal: 'left' };
                for (let i = 2; i <= rowData.length; i++) {
                    const cell = dataRow.getCell(i);
                    cell.numFmt = numFmt;
                    cell.alignment = { horizontal: 'right' };
                    if (i === rowData.length) {
                        cell.font = { name: fontName, bold: true, size: 11 };
                        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF3E0' } };
                    }
                }

                // 3.3: แถว MoM%
                if (showMoM.value) {
                    const momRowData: (string | number)[] = [`  (MoM%)`];
                    displayedMonths.value.forEach(month => {
                        const momValue = getMoM_VerticalTotal(month.value, region, fieldKey);
                        // แปลง 10.5 -> 0.105 (สำหรับ Excel)
                        momRowData.push(momValue !== null ? momValue / 100 : '-');
                    });
                    momRowData.push(''); // คอลัมน์ "รวม" ของ MoM ไม่มี

                    const momRow = worksheet.addRow(momRowData);
                    momRow.font = { name: fontName, italic: true, size: 10, color: { argb: 'FF555555' } };

                    // สไตล์สำหรับแถว MoM
                    momRow.getCell(1).alignment = { horizontal: 'left' };
                    for (let i = 2; i <= momRowData.length; i++) {
                        const cell = momRow.getCell(i);
                        if (typeof cell.value === 'number') {
                            cell.numFmt = '0.0"%"'; // Format เป็น %
                            cell.alignment = { horizontal: 'right' };
                        } else {
                            cell.alignment = { horizontal: 'center' };
                        }
                    }
                }
            });
        });

        // --- 4. Grand Total Rows ---
        const totalTitleRow = worksheet.addRow(['รวม (แนวตั้ง)']); // ⭐️ เปลี่ยนข้อความ
        totalTitleRow.font = { name: fontName, bold: true, size: 12, color: { argb: 'FFF8285A' } };
        totalTitleRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.mergeCells(totalTitleRow.number, 1, totalTitleRow.number, headerRowData.length);

        dataTypes.forEach((field) => {
            const metricLabel = dataTypeLabels[field];
            const rowData: (string | number)[] = [`${metricLabel} (รวม)`];
            const numFmt = getNumFmt(field);
            const fieldKey = field as keyof RegionMetrics;

            // ผลรวมแนวตั้ง (รายเดือน)
            displayedMonths.value.forEach(month => {
                rowData.push(getMonthlyGrandTotal(month.value, fieldKey));
            });

            // ผลรวมทั้งหมด (Grand Total)
            rowData.push(getMonthlyTabGrandTotal(fieldKey));

            const totalRow = worksheet.addRow(rowData);
            totalRow.font = { name: fontName, bold: true, size: 12, color: { argb: 'FFF8285A' } };

            totalRow.getCell(1).alignment = { horizontal: 'left' };
            for (let i = 2; i <= rowData.length; i++) {
                const cell = totalRow.getCell(i);
                cell.numFmt = numFmt;
                cell.alignment = { horizontal: 'right' };
                if (i === rowData.length) {
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF3E0' } };
                }
            }

            // 4.1: แถว MoM% สำหรับ Total
            if (showMoM.value) {
                const momRowData: (string | number)[] = [`  (MoM%)`];
                displayedMonths.value.forEach(month => {
                    // ⭐️ ใช้ getMonthTotalMoM (Helper ที่มีอยู่แล้ว)
                    const momValue = getMonthTotalMoM(month.value, fieldKey);
                    momRowData.push(momValue !== null ? momValue / 100 : '-');
                });
                momRowData.push(''); // "รวม" ไม่มี MoM

                const momRow = worksheet.addRow(momRowData);
                momRow.font = { name: fontName, italic: true, size: 10, color: { argb: 'FF555555' } };
                momRow.getCell(1).alignment = { horizontal: 'left' };
                for (let i = 2; i <= momRowData.length; i++) {
                    const cell = momRow.getCell(i);
                    if (typeof cell.value === 'number') {
                        cell.numFmt = '0.0"%"';
                        cell.alignment = { horizontal: 'right' };
                    } else {
                        cell.alignment = { horizontal: 'center' };
                    }
                }
            }
        });

        // --- 5. Set Column Widths ---
        worksheet.getColumn(1).width = 30; // คอลัมน์ (ภูมิภาค / Metric)
        for (let i = 2; i <= headerRowData.length; i++) {
            worksheet.getColumn(i).width = 18; // คอลัมน์เดือน และ รวม
        }

        // --- 6. Write file ---
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheet.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${dynamicFilename.value}.xlsx`; // ⭐️ ใช้ dynamicFilename
        a.click();
        window.URL.revokeObjectURL(url);

        snackbarText.value = 'สร้างไฟล์ Excel สำเร็จแล้ว';
        snackbarColor.value = 'success';
        showSnackbar.value = true;

    } catch (err: any) {
        console.error('Error exporting to Excel:', err);
        snackbarText.value = `ไม่สามารถสร้าง Excel ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}`;
        snackbarColor.value = 'error';
        showSnackbar.value = true;
    } finally {
        exportLoading.value = false;
    }
};



// ⭐️ [ใหม่] (8.D) `exportToPdf` (จาก house.vue, ปรับปรุง ID)
const exportToPdf = async () => {
    exportLoading.value = true;
    showSnackbar.value = false;

    // ⭐️ [ปรับปรุง] เปลี่ยน ID เป้าหมาย
    const tableElement = document.getElementById('region-table-to-export');
    if (!tableElement) {
        console.error('Table element not found! (ID: region-table-to-export)');
        snackbarText.value = 'ไม่พบองค์ประกอบตาราง (Table element not found!)';
        snackbarColor.value = 'error';
        showSnackbar.value = true;
        exportLoading.value = false;
        return;
    }

    try {
        // === 1. โหลดทรัพยากร (ฟอนต์) ===
        const fontUrl = '/fonts/THSarabunNew.ttf';
        let fontBase64 = '';
        try {
            const fontResponse = await fetch(fontUrl);
            if (!fontResponse.ok) throw new Error('Failed to fetch local font: /fonts/THSarabunNew.ttf');
            const fontBlob = await fontResponse.blob();
            const fontDataURL = await blobToDataURL(fontBlob);
            fontBase64 = fontDataURL.split(',')[1];
            if (!fontBase64) throw new Error('Failed to parse Base64 from font Data URL');

        } catch (fontErr: any) {
            throw new Error(`Font loading failed: ${fontErr.message}`);
        }

        const logoUrl = '/assets/images/image-28-2.png';
        let logoDataURL = '';
        try {
            const logoResponse = await fetch(logoUrl);
            if (!logoResponse.ok) throw new Error('Logo file not found in /assets/images/image-28-2.png');
            const logoBlob = await logoResponse.blob();
            logoDataURL = await blobToDataURL(logoBlob);
        } catch (imgErr) {
            console.error(imgErr);
        }

        // === 2. สร้าง PDF และตั้งค่าฟอนต์ ===
        const pdf = new jsPDF('p', 'mm', 'a4'); // ⭐️ ใช้ 'p' (แนวตั้ง)
        const fontName = 'THSarabunNew';
        const fontStyle = 'normal';

        pdf.addFileToVFS('THSarabunNew.ttf', atob(fontBase64));
        pdf.addFont('THSarabunNew.ttf', fontName, fontStyle);
        pdf.setFont(fontName, fontStyle);

        // === 3. สร้างหน้าปก (ข้อความ) ===
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageCenter = pageWidth / 2;

        let currentY = 90;

        if (logoDataURL) {
            const logoWidth = 60;
            const logoHeight = 70;
            const logoX = pageCenter - (logoWidth / 2);
            pdf.addImage(logoDataURL, 'PNG', logoX, currentY, logoWidth, logoHeight);
            currentY += logoHeight + 15;
        } else {
            currentY = 100;
        }

        pdf.setFontSize(20);

        // ⭐️ [ปรับปรุง] เปลี่ยน Title
        const title = `ตารางสรุปยอดรายเดือน (ภูมิภาค x เดือน) ${filterSubtitle.value}`;

        const splitTitle = pdf.splitTextToSize(title, pageWidth - 40);
        pdf.text(splitTitle, pageCenter, currentY, { align: 'center' });
        currentY += (splitTitle.length * 7);

        const today = new Date();
        const day = today.getDate();
        const monthIndex = today.getMonth();
        const year = today.getFullYear() + 543;
        const thaiMonths = [
            "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
            "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
        ];
        const month = thaiMonths[monthIndex];
        const dateString = `อัพเดตข้อมูลวันที่ ${day} ${month} ${year}`;

        currentY += 10;
        pdf.setFontSize(15);
        pdf.text(dateString, pageCenter, currentY, { align: 'center' });
        // === จบหน้าปก ===

        // 7. ถ่ายรูปตาราง
        const canvas = await html2canvas(tableElement, {
            scale: 2,
            useCORS: true,
            logging: false,
        });

        const imgData = canvas.toDataURL('image/png');

        // 8. เพิ่มหน้าใหม่สำหรับตาราง
        pdf.addPage();

        // 9. คำนวณสัดส่วน
        const pageMargin = 10;
        const pdfWidth = pdf.internal.pageSize.getWidth() - (pageMargin * 2);
        const pdfHeight = pdf.internal.pageSize.getHeight() - (pageMargin * 2);

        const imgWidth = pdfWidth;
        const imgHeight = canvas.height * imgWidth / canvas.width;

        let heightLeft = imgHeight;
        let position = pageMargin;

        pdf.addImage(imgData, 'PNG', pageMargin, position, imgWidth, imgHeight);
        heightLeft -= pdfHeight;

        // 10. วนลูปเพิ่มหน้า (เหมือนเดิม)
        while (heightLeft > 0) {
            position = heightLeft - imgHeight + pageMargin;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', pageMargin, position, imgWidth, imgHeight);
            heightLeft -= pdfHeight;
        }

        // 11. บันทึกไฟล์
        pdf.save(`${dynamicFilename.value}.pdf`);

        snackbarText.value = 'สร้างไฟล์ PDF สำเร็จแล้ว';
        snackbarColor.value = 'success';
        showSnackbar.value = true;

    } catch (err: any) {
        console.error('Error exporting to PDF:', err);
        snackbarText.value = `ไม่สามารถสร้าง PDF ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}`;
        snackbarColor.value = 'error';
        showSnackbar.value = true;

    } finally {
        exportLoading.value = false;
    }
};


const filterSubtitle = computed(() => {
    const yearText = `ประจำปี ${selectedYear.value}`;
    const sortedMonthsKey = [...selectedMonths.value].sort((a, b) => a - b).join(',');

    const validMonthValues = monthOptions.value.map(m => m.value);

    const isQ1Full = [1, 2, 3].every(m => validMonthValues.includes(m));
    const isQ2Full = [4, 5, 6].every(m => validMonthValues.includes(m));
    const isQ3Full = [7, 8, 9].every(m => validMonthValues.includes(m));
    const isQ4Full = [10, 11, 12].every(m => validMonthValues.includes(m));

    const q1Months = [1, 2, 3].filter(m => validMonthValues.includes(m)).join(',');
    const q2Months = [4, 5, 6].filter(m => validMonthValues.includes(m)).join(',');
    const q3Months = [7, 8, 9].filter(m => validMonthValues.includes(m)).join(',');
    const q4Months = [10, 11, 12].filter(m => validMonthValues.includes(m)).join(',');

    if (sortedMonthsKey === q1Months && isQ1Full && q1Months.length > 0) return `(${yearText} - ไตรมาส 1 (มกราคม - มีนาคม))`;
    if (sortedMonthsKey === q2Months && isQ2Full && q2Months.length > 0) return `(${yearText} - ไตรมาส 2 (เมษายน - มิถุนายน))`;
    if (sortedMonthsKey === q3Months && isQ3Full && q3Months.length > 0) return `(${yearText} - ไตรมาส 3 (กรกฎาคม - กันยายน))`;
    if (sortedMonthsKey === q4Months && isQ4Full && q4Months.length > 0) return `(${yearText} - ไตรมาส 4 (ตุลาคม - ธันวาคม))`;

    const yearAD = selectedYear.value - 543;
    const allMonthsCurrentYear = allMonthItems.map((m) => m.value).slice(0, currentJsMonth).join(',');
    const allMonthsPastYear = allMonthItems.map((m) => m.value).join(',');

    if (sortedMonthsKey === allMonthsCurrentYear || sortedMonthsKey === allMonthsPastYear) {
        const allOption = quarterOptions.value.find((q) => q.value === 'all');
        return `(${yearText} - ${allOption ? allOption.title : 'ทุกเดือน'})`;
    }

    if (selectedMonths.value.length > 0) {
        const sortedMonthValues = [...selectedMonths.value].sort((a, b) => a - b);
        const firstMonthValue = sortedMonthValues[0];
        const lastMonthValue = sortedMonthValues[sortedMonthValues.length - 1];
        const firstMonth = allMonthItems.find((m) => m.value === firstMonthValue);
        const lastMonth = allMonthItems.find((m) => m.value === lastMonthValue);

        if (!firstMonth || !lastMonth) {
            return `(${yearText} - กำลังเลือกเดือน...)`;
        }
        if (firstMonthValue === lastMonthValue) {
            return `(${yearText} - เดือน ${firstMonth.title})`;
        } else {
            return `(${yearText} - เดือน ${firstMonth.title} - ${lastMonth.title})`;
        }
    }
    return `(${yearText} - ยังไม่ได้เลือกเดือน)`;
});


function getSummaryContributionPercent(region: string, field: keyof RegionMetrics): string {

    if (field === 'price_per_sqm') {
        return "";
    }

    const regionTotal = getSummaryVerticalTotal(region, field);
    const grandTotal = getSummaryGrandTotal(field);

    if (grandTotal === 0 || regionTotal === 0) {
        return "";
    }

    const percent = (regionTotal / grandTotal) * 100;

    return `<span class="contribution-percent" style="font-size: 10px; color: ##28a745; font-weight: 400; margin-left: 4px; white-space: nowrap;">(${percent.toFixed(1)}%)</span>`;
}

// ⭐️ [ใหม่] Helper (YoY) สำหรับ "ผลรวมแนวนอน" (คอลัมน์ รวม) ของ 1 Region
// (ฟังก์ชันนี้จำเป็นสำหรับแถว "ภาค...")
// ⭐️ [ใหม่] Helper (YoY) สำหรับ "ผลรวมแนวนอน" (คอลัมน์ รวม) ของ 1 Region
function getRegionHorizontalTotalYoY(region: string, field: keyof RegionMetrics): number | null {
    // 1. หาค่าปัจจุบัน (YTD) (จาก Helper ที่มีอยู่แล้ว)
    const currentValue = getRegionHorizontalTotal(region, field);

    // 2. หาค่าปีก่อน (YTD)
    let previousValue = 0;
    let previousTotalValue = 0;
    let previousTotalArea = 0;

    for (const month of displayedMonths.value) {
        for (const range of priceRanges) {
            if (field === 'price_per_sqm') {
                previousTotalValue += getRawDataMonthly(previousYearDetailedTableData.value, month.value, region, range, 'value');
                previousTotalArea += getRawDataMonthly(previousYearDetailedTableData.value, month.value, region, range, 'area');
            }
            // ⭐️ [แก้ไข] ⭐️
            // เปลี่ยนจาก 'else if (field !== 'price_per_sqm')' เป็น 'else'
            else {
                // TS รู้ว่า 'field' ในที่นี้คือ 'unit' | 'value' | 'area'
                // จึงไม่ต้องใช้ "as" อีกต่อไป
                previousValue += getRawDataMonthly(previousYearDetailedTableData.value, month.value, region, range, field);
            }
        }
    }

    // 3. คำนวณ %
    if (field === 'price_per_sqm') {
        const previousAvg = previousTotalArea > 0 ? (previousTotalValue / previousTotalArea) : 0;
        return calculateMoMPercent(currentValue, previousAvg);
    }

    return calculateMoMPercent(currentValue, previousValue);
}

// ⭐️ [ใหม่] Helper สำหรับจัดรูปแบบ % YoY/YTD เป็น HTML (สำหรับตาราง)
function formatYoYSpan(percent: number | null): string {
    if (percent === null) return "";

    const percentStr = percent.toFixed(0); // YTD/YoY ไม่เอาทศนิยม

    if (percent > 0) {
        return `<span class="yoy-percent text-success">(+${percentStr}%)</span>`;
    } else if (percent < 0) {
        return `<span class="yoy-percent text-error">(${percentStr}%)</span>`;
    } else {
        return ''; // ไม่แสดง 0%
    }
}

const dynamicUnitSubtitle = computed(() => {
    const selectedKey = selectedHighlight.value;

    if (selectedKey === 'unit') return '(หน่วย : หลัง)';
    if (selectedKey === 'area') return '(หน่วย : ตร.ม.)';
    if (selectedKey === 'price_per_sqm') return '(หน่วย : บาท / ตร.ม.)';

    // ⭐️ [แก้ไข]
    // ถ้า selectedKey คือ 'value' หรือ null (ค่าเริ่มต้น)
    // ให้แสดง (หน่วย : บาท)
    if (selectedKey === 'value' || selectedKey === null) {
        return '(หน่วย : บาท)';
    }

    return ''; // (เผื่อไว้เฉยๆ)
});

</script>

<template>
    <v-row>

        <v-snackbar v-model="showSnackbar" :timeout="snackbarTimeout" :color="snackbarColor" location="top end"
            variant="elevated">
            {{ snackbarText }}
            <template v-slot:actions>
                <v-btn color="white" variant="text" @click="showSnackbar = false">
                    ปิด
                </v-btn>
            </template>
        </v-snackbar>


        <v-col cols="12" sm="12" lg="12">
            <div class="mt-3 mb-6">
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">รายงานยอดเซ็นสัญญา (มูลค่า x ภูมิภาค)</h3>
                            <ul
                                class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <li class="v-breadcrumbs-item" text="Home">
                                    <a class="v-breadcrumbs-item--link" href="#">
                                        <p>หน้าแรก</p>
                                    </a>
                                </li>
                                <li class="v-breadcrumbs-divider">
                                    <i class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true" style="font-size: 15px; height: 15px; width: 15px"></i>
                                </li>
                                <li class="v-breadcrumbs-item" text="Dashboard">
                                    <a class="v-breadcrumbs-item--link" href="#">
                                        <p>รายงานยอดเซ็นสัญญา (มูลค่า x ภูมิภาค)</p>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </v-col>



        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10">
                <v-card-text>
                    <v-row>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedYear" :items="yearOptions" label="ปี (พ.ศ.)" density="compact"
                                variant="outlined" hide-details />
                        </v-col>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedQuarter" :items="quarterOptions" item-title="title"
                                item-value="value" label="ไตรมาส" density="compact" variant="outlined" hide-details />
                        </v-col>
                        <v-col cols="12" sm="4" md="4">
                            <v-select v-model="selectedMonths" :items="monthOptions" item-title="title"
                                item-value="value" label="เดือน (เลือกได้หลายเดือน)" multiple chips closable-chips
                                density="compact" variant="outlined" hide-details />
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div v-for="field in dataTypes" :key="field" class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-0">
                    <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated"
                        @click="highlightRow(field)" style="cursor: pointer;" hover
                        :class="{ 'card-is-active': selectedHighlight === field }">
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4">
                                <button type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                    :class="{
                                        'bg-primary': field === 'unit',
                                        'bg-secondary': field === 'value',
                                        'bg-error': field === 'area',
                                        'bg-warning': field === 'price_per_sqm'
                                    }" dark>
                                    <svg v-if="field === 'unit'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" />
                                            <path stroke-linecap="round" d="M12 15v3" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="field === 'value'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                                            <path stroke-linecap="round"
                                                d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="field === 'area'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                                            <path
                                                d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" />
                                            <path stroke-linejoin="round"
                                                d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" />
                                        </g>
                                    </svg>
                                    <svg v-else-if="field === 'price_per_sqm'" xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path
                                                d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808-1.123C21.007 7.109 22 7.506 22 8s-.993-.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" />
                                            <path
                                                d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" />
                                            <path
                                                d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" />
                                        </g>
                                    </svg>
                                </button>
                                <div class="">
                                    <div class="d-flex align-end ga-2">
                                        <h2 class="text-h4" style="line-height: 1.1;">
                                            {{ formatValue(summaryCardData[field], field) }}
                                        </h2>
                                    </div>

                                    <span v-if="summaryCardYTDData[field]" class="mom-card" :class="{
                                        'text-success': summaryCardYTDData[field] && summaryCardYTDData[field].includes('+'),
                                        'text-error': summaryCardYTDData[field] && summaryCardYTDData[field].includes('-')
                                    }" style="font-size: 9px; font-weight: 400; line-height: 1.2; display: block;">
                                        {{ summaryCardYTDData[field] }}
                                    </span>

                                    <p class="textSecondary text-15" style="line-height: 1.2;">
                                        {{ dataTypeLabels[field] }} (รวม)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </v-col>

        <v-col cols="12">
            <VCard elevation="10">
                <v-card-text>
                    <div class="v-row ">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">รายงานยอดเซ็นสัญญา {{ filterSubtitle }}</h3>
                                    <h5 class="card-subtitle" style="text-align: left">{{ dynamicUnitSubtitle }}</h5>
                                </div>
                            </div>
                        </div>

                        <div class="v-col-md-4 v-col-12">
                            <v-row v-if="chartMoMData.hasPreviousMonth" align="center" justify="end" class="mt-0">
                                <v-col cols="auto" class="text-right">
                                    <h3 class="card-title" :class="getPercentageColor(chartMoMData.percent)"
                                        style="font-size: 1.25rem;">
                                        {{ formatPercentage(chartMoMData.percent) }}
                                    </h3>
                                    <h5 class="card-subtitle text-grey-darken-1"> {{ chartMoMData.label }} </h5>
                                </v-col>
                            </v-row>
                            <div v-else-if="chartMoMData.label" class="text-right text-grey mt-2 pa-2">
                                <h5 class="card-subtitle text-grey-darken-1">{{ chartMoMData.label }}</h5>
                            </div>
                        </div>
                    </div>

                    <v-row class="mt-0">
                        <v-col cols="12" md="12">
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2">
                                <v-switch v-model="showMoM" label="แสดง MoM%" color="primary" density="compact"
                                    hide-details class="flex-shrink-0"></v-switch>

                                <v-btn-toggle v-model="selectedTimeRange" variant="outlined" density="compact"
                                    color="primary" class="flex-wrap">
                                    <v-btn v-for="range in timeRangeOptions" :key="range.value" :value="range.value"
                                        size="small">
                                        {{ range.label }}
                                    </v-btn>
                                </v-btn-toggle>
                            </div>
                        </v-col>
                    </v-row>

                    <div class="mt-5">
                        <apexchart type="polarArea" :options="polarAreaOptions" :series="polarAreaSeries"
                            height="400" />
                    </div>
                </v-card-text>
            </VCard>
        </v-col>

        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10" style="position: relative;"> <v-overlay :model-value="exportLoading"
                    class="align-center justify-center " persistent scrim="white" style="opacity: 0.8;">
                    <div class="text-center">
                        <v-progress-circular color="primary" indeterminate size="64"></v-progress-circular>
                        <h4 class="text-primary mt-3">กำลังสร้างไฟล์รายงาน...</h4>
                    </div>
                </v-overlay><br></br>

                <v-card-title class="d-flex justify-space-between align-center flex-wrap ">
                    <div>
                        <h3 class="card-title mb-1">ตารางยอดเซ็นสัญญา {{ filterSubtitle }}</h3>
                        <h5 class="card-subtitle" style="text-align: left">{{ dynamicUnitSubtitle }}</h5>
                    </div>
                    <div class="d-flex justify-end ga-2 mt-2">
                        <v-btn color="primary" variant="outlined" @click="exportToExcel" class="v-btn--size-small"
                            :loading="exportLoading">
                            <v-icon start>mdi-file-excel</v-icon>
                            Export Excel
                        </v-btn>

                        <v-btn color="primary" variant="outlined" @click="exportToPdf" class="v-btn--size-small"
                            :loading="exportLoading">
                            <v-icon start>mdi-file-pdf-box</v-icon>
                            Export PDF
                        </v-btn>
                    </div>
                </v-card-title>

                <v-card-text>
                    <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                        <div class="v-table__wrapper" style="overflow-x: auto">
                            <table id="region-table-to-export">
                                <thead style="background-color: #f5f5f5">
                                    <tr>
                                        <th class="text-h6" style="min-width: 80px; text-align: left;"></th>
                                        <th v-for="month_item in displayedMonths" :key="month_item.value" class="text-p"
                                            style="font-size: 13px; text-align: center">
                                            {{ month_item.short }}
                                        </th>
                                        <th class="text-p"
                                            style="font-size: 13px; font-weight: 600; background-color: #fff3e0; text-align: center">
                                            รวม
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <template v-for="region in regions" :key="region">
                                        <tr class="month-item" style="background-color: #fcf8ff">
                                            <td style="text-align: left;">
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #725af2">{{ region
                                                    }}</h6>
                                            </td>
                                            <td :colspan="displayedMonths.length + 1"></td>
                                        </tr>

                                        <tr class="month-item" v-for="field in dataTypes" :key="region + field"
                                            v-show="isRowVisible(field)" :style="getHighlightStyle(field)">
                                            <td style="text-align: left;">
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 400; padding-left: 15px">
                                                    {{ dataTypeLabels[field] }}
                                                </h6>
                                            </td>

                                            <td v-for="month_item in displayedMonths"
                                                :key="region + field + month_item.value"
                                                style="text-align: right; vertical-align: top;">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 400;">
                                                        {{ formatValue(getMonthlyVerticalTotal(month_item.value, region,
                                                        field), field) }}
                                                    </h6>
                                                    <span v-if="showMoM" style="font-size: 9px !important;"
                                                        v-html="getMoMFormatted_VerticalTotal(month_item.value, region, field)"></span>
                                                </div>
                                            </td>

                                            <td style="background-color: #fff3e0; text-align: right; vertical-align: top;"
                                                :style="getHighlightStyle(field)">
                                                <div>
                                                    <h6 class="text-p" style="font-size: 12px; font-weight: 600">
                                                        {{ formatValue(getRegionHorizontalTotal(region, field), field)
                                                        }}
                                                    </h6>
                                                    <span v-if="showMoM" style="font-size: 9px !important;"
                                                        v-html="formatYoYSpan(getRegionHorizontalTotalYoY(region, field))">
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr class="month-item" style="background-color: #fcf8ff;" v-for="field in dataTypes"
                                        :key="'total-' + field" v-show="isRowVisible(field)"
                                        :style="getHighlightStyle(field)">
                                        <td style="text-align: left;">
                                            <h6 class="text-p"
                                                style="font-size: 13px; font-weight: 600; color: #f8285a">
                                                {{ dataTypeLabels[field] }} (รวม)
                                            </h6>
                                        </td>

                                        <td v-for="month_item in displayedMonths"
                                            :key="'total-' + month_item.value + field"
                                            style="text-align: right; vertical-align: top;">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 600; color: #f8285a">
                                                    {{ formatValue(getMonthlyGrandTotal(month_item.value, field), field)
                                                    }}
                                                </h6>
                                                <span v-if="showMoM" style="font-size: 9px !important;"
                                                    v-html="getMoMFormatted_GrandTotal(month_item.value, field)"></span>
                                            </div>
                                        </td>

                                        <td style="background-color: #fff3e0; text-align: right; vertical-align: top;"
                                            :style="getHighlightStyle(field)">
                                            <div>
                                                <h6 class="text-p"
                                                    style="font-size: 12px; font-weight: 800; color: #f8285a">
                                                    {{ formatValue(getMonthlyTabGrandTotal(field), field) }}
                                                </h6>
                                                <span v-if="showMoM"
                                                    style="font-size: 9px !important; font-weight: 600;"
                                                    v-html="formatYoYSpan(getSummaryGrandTotalYoY(field))">
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

    </v-row>
</template>

<style scoped>
.text-subtitle-1 {
    font-size: 14px;
}

.text-h6 {
    font-size: 18px;
}

th.text-left,
td.text-left {
    text-align: left !important;
    padding-left: 16px !important;
}

th.text-right,
td.text-right {
    text-align: right !important;
    padding-right: 16px !important;
}

.month-item td,
.month-item th {
    padding: 8px !important;
    border-bottom: 1px solid #eee;
}

:deep(.v-slide-group__container) {
    background-color: #FFFFFF;
    border-radius: 5px;
}

:deep(.v-tab) {
    background-color: #FFFFFF;
    color: #000000 !important;
    opacity: 0.8;
    border-radius: 5px;
}

:deep(.v-tab:hover) {
    background-color: #2196F3 !important;
    color: #FFFFFF !important;
    opacity: 1;
    border-radius: 5px;
}

:deep(.v-tab--selected) {
    background-color: #2196F3 !important;
    color: #FFFFFF !important;
    opacity: 1;
    border-radius: 5px;
}

.mom-percent {
    font-size: 5px;
    margin-left: 4px;
    white-space: nowrap;
}

.text-success {
    color: #28a745;
}

.text-error {
    color: #ed0b22;
}

.mom-card {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 5px;
    white-space: nowrap;
}

.month-item td {
    vertical-align: middle;
}

/* ⭐️ [ใหม่] CSS สำหรับการ์ดที่คลิกได้ (เหมือน house.vue) ⭐️ */
.v-card[style*="cursor: pointer"] {
    transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
}

.v-card[style*="cursor: pointer"]:hover,
.v-card.card-is-active {
    background-color: #E3F2FD !important;
    transform: translateY(-2px);
}

.v-card[style*="cursor: pointer"]:hover .text-h4,
.v-card[style*="cursor: pointer"]:hover .textSecondary,
.v-card.card-is-active .text-h4,
.v-card.card-is-active .textSecondary {
    color: #1E88E5 !important;
}

/* ⭐️ [ใหม่] CSS สำหรับปุ่ม TimeRange (จาก house.vue) ⭐️ */
.flex-wrap {
    flex-wrap: wrap;
    height: auto !important;
}
</style>