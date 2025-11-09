<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import ExcelJS from 'exceljs';
import type { BorderStyle, Cell } from 'exceljs';

// --- (1) ส่วนฟิลเตอร์และเวลา (เหมือน house.vue) ---
const jsDate = new Date();
const currentJsYear = jsDate.getFullYear();
const currentJsMonth = jsDate.getMonth() + 1; // (1-12)

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

// --- (2) โครงสร้างข้อมูล ---

// (2.1) โครงสร้างข้อมูล Metrics
interface RegionMetrics {
    unit: number;
    value: number; // ⭐️ Backend ใช้ 'value' ไม่ใช่ 'total_value'
    area: number; // ⭐️ Backend ใช้ 'area' ไม่ใช่ 'usable_area'
    price_per_sqm: number;
}
// ⭐️ data[Region][PriceRange]
// (ข้อมูลสรุปสำหรับ Chart, Cards, และตาราง Grand Total)
const rawData = ref<Record<string, Record<string, { unit: number; value: number; area: number }>>>({});

// ⭐️ [เพิ่มกลับมา] โครงสร้างข้อมูลสำหรับตารางรายเดือน
// detailedTableData[Month][Region][PriceRange]
const detailedTableData = ref<Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>>>({});


// (2.2) ค่าคงที่ (แกนแถว)
const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
const dataTypes: (keyof RegionMetrics)[] = ['unit', 'value', 'area', 'price_per_sqm'];
const dataTypeLabels: Record<string, string> = {
    unit: 'จำนวนหลัง',
    value: 'มูลค่ารวม', // ⭐️ เปลี่ยน label
    area: 'พื้นที่ใช้สอย', // ⭐️ เปลี่ยน label
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
const yearOptions = ref(Array.from({ length: 5 }, (_, i) => currentJsYear + 543 - i));

// ⭐️ [ใหม่] State สำหรับ Active Tab
const activeTab = ref('summary');

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

// (4.1) ⭐️ [ปรับปรุง] ดึงข้อมูล "สรุป" (สำหรับ Chart, Cards, และ Pivot Table)
// (เปลี่ยนชื่อกลับเป็น fetchData)
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

// (4.2) ⭐️ [เพิ่มกลับมา] fetchDetailedTableData
const fetchDetailedTableData = async () => {
    if (!userId && userRole !== 'admin') return;
    
    const monthsToFetch = [...selectedMonths.value];
    if (monthsToFetch.length === 0 || !selectedYear.value) {
        detailedTableData.value = {};
        return;
    }

    const newData: Record<number, Record<string, Record<string, { unit: number; value: number; area: number }>>> = {};
    
    // ⭐️ [ปรับปรุง] ดึงข้อมูลเดือนก่อนหน้า (ถ้ามี)
    const priorMonthsToFetch: number[] = [];
    if (selectedMonths.value.includes(1)) {
        // ⭐️ (ยังไม่รองรับการดึงข้อมูลข้ามปี)
        // (ถ้าต้องการข้ามปี ต้องเพิ่ม logic ดึง selectedYear - 1)
    }
    selectedMonths.value.forEach(m => {
        const priorMonth = m - 1;
        if (priorMonth > 0 && !monthsToFetch.includes(priorMonth)) {
             priorMonthsToFetch.push(priorMonth);
        }
    });
    
    const allMonthsToFetch = [...new Set([...monthsToFetch, ...priorMonthsToFetch])];


    for (const month of allMonthsToFetch) { // ⭐️ ใช้ allMonthsToFetch
        try {
            const res = await fetch('https://uat.hba-sales.org/backend/get_contract_summary_main.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    user_id: userId,
                    buddhist_year: selectedYear.value,
                    months: [month], // ⭐️ ส่งทีละเดือน
                    role: userRole
                })
            });
            if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
            
            // data for one month: { "Region1": { "PriceRange1": ... }, ... }
            const data = await res.json();
            newData[month] = data;

        } catch (err) {
            console.error(`❌ Error fetching data for month ${month}:`, err);
            newData[month] = {}; // ใส่ค่าว่างถ้า fetch ไม่สำเร็จ
        }
    }
    detailedTableData.value = newData;
    console.log('✅ ข้อมูลดิบ (รายเดือน + เดือนก่อนหน้า) ที่ได้รับ:', newData);
};


// --- (5) Watchers (เหมือน house.vue) ---
// ⭐️ [ปรับปรุง]
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

// ⭐️ [ปรับปรุง]
watch(selectedYear, () => {
    const validMonths = monthOptions.value.map((m) => m.value);
    selectedMonths.value = selectedMonths.value.filter((m) => validMonths.includes(m));

    if (selectedQuarter.value === 'all') {
        updateToAllMonths(); 
    } else {
        // ⭐️ บังคับให้ logic ของ selectedQuarter ทำงานอีกครั้ง
        const currentQuarter = selectedQuarter.value;
        selectedQuarter.value = ''; 
        selectedQuarter.value = currentQuarter;
    }
});

// ⭐️ [ปรับปรุง]
watch(
    selectedMonths,
    (newMonths, oldMonths) => {
        // ⭐️ [ใหม่] ถ้าจำนวนเดือนเปลี่ยน ให้รีเซ็ตแท็บกลับไปที่ 'summary'
        if (newMonths.join(',') !== oldMonths.join(',')) {
            activeTab.value = 'summary';
        }

        const sortedMonths = [...newMonths].sort((a, b) => a - b).join(',');
        
        const validMonthValues = monthOptions.value.map(m => m.value);
        const q1Months = [1, 2, 3].filter(m => validMonthValues.includes(m)).join(',');
        const q2Months = [4, 5, 6].filter(m => validMonthValues.includes(m)).join(',');
        const q3Months = [7, 8, 9].filter(m => validMonthValues.includes(m)).join(',');
        const q4Months = [10, 11, 12].filter(m => validMonthValues.includes(m)).join(',');

        if (sortedMonths === q1Months && q1Months.length > 0) selectedQuarter.value = 'Q1';
        else if (sortedMonths === q2Months && q2Months.length > 0) selectedQuarter.value = 'Q2';
        else if (sortedMonths === q3Months && q3Months.length > 0) selectedQuarter.value = 'Q3';
        else if (sortedMonths === q4Months && q4Months.length > 0) selectedQuarter.value = 'Q4';
        else {
            const allMonthsCurrentYear = allMonthItems.map((m) => m.value).slice(0, currentJsMonth).join(',');
            const allMonthsPastYear = allMonthItems.map((m) => m.value).join(',');

            if (sortedMonths === allMonthsCurrentYear || sortedMonths === allMonthsPastYear) {
                if (selectedQuarter.value !== 'all') {
                    isUpdatingFromMonths.value = true;
                    selectedQuarter.value = 'all';
                }
            } else if (selectedQuarter.value !== 'all') {
                isUpdatingFromMonths.value = true;
                selectedQuarter.value = 'all';
            }
        }
        
        // ⭐️ [ปรับปรุง] เรียก fetch ทั้งสองฟังก์ชัน
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

// ⭐️ (6.B.7) [ใหม่] [แถวแนวนอน-สำหรับตารางใหม่] คำนวณยอดรวมของ 1 Region (รวมทุกเดือนที่แสดง)
function getRegionHorizontalTotal(region: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;

    // วนลูปเฉพาะเดือนที่แสดง
    for (const month of displayedMonths.value) {
        // ⭐️ ใช้ getMonthlyVerticalTotal (ที่มีอยู่แล้ว) 
        // ⭐️ เพื่อดึงยอดรวม (ทุก PriceRange) ของ Region นั้น / ในเดือนนั้น
        totalUnit += getMonthlyVerticalTotal(month.value, region, 'unit');
        totalValue += getMonthlyVerticalTotal(month.value, region, 'value');
        totalArea += getMonthlyVerticalTotal(month.value, region, 'area');
    }

    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        // คำนวณเฉลี่ยจากยอดรวม
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}

// ⭐️ (6.B.8) [ใหม่] [รวมทั้งหมด-สำหรับตารางใหม่] คำนวณยอดรวม Grand Total (รวมทุกเดือนที่แสดง)
function getMonthlyTabGrandTotal(field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;

    // วนลูปเฉพาะเดือนที่แสดง
    for (const month of displayedMonths.value) {
        // ⭐️ ใช้ getMonthlyGrandTotal (ที่มีอยู่แล้ว)
        // ⭐️ เพื่อดึงยอดรวม (ทุก Region, ทุก PriceRange) ของเดือนนั้น
        totalUnit += getMonthlyGrandTotal(month.value, 'unit');
        totalValue += getMonthlyGrandTotal(month.value, 'value');
        totalArea += getMonthlyGrandTotal(month.value, 'area');
    }

    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        // คำนวณเฉลี่ยจากยอดรวม
        return totalArea > 0 ? (totalValue / totalArea) : 0;
    }
    return 0;
}


function getSummaryNumericValue(region: string, range: string, field: 'unit' | 'value' | 'area'): number {
    return Number(rawData.value?.[region]?.[range]?.[field] || 0);
}

// (6.A.2) คำนวณค่าสำหรับช่องในตาราง (รวม price_per_sqm)
function getSummaryCalculatedMetrics(region: string, range: string): RegionMetrics {
    const unit = getSummaryNumericValue(region, range, 'unit');
    const value = getSummaryNumericValue(region, range, 'value');
    const area = getSummaryNumericValue(region, range, 'area');
    const price_per_sqm = area > 0 ? (value / area) : 0; // ⭐️ ใช้ / ธรรมดา
    
    return { unit, value, area, price_per_sqm };
}

// (6.A.3) [แถวแนวนอน] คำนวณยอดรวมของ 1 PriceRange (รวมทุก Region)
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
        return totalArea > 0 ? (totalValue / totalArea) : 0; // ⭐️ ใช้ / ธรรมดา
    }
    return 0;
}


// (6.A.4) [คอลัมน์แนวตั้ง] คำนวณยอดรวมของ 1 Region (รวมทุก PriceRange)
function getSummaryVerticalTotal(region: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;

    for (const range of priceRanges) {
        const metrics = rawData.value?.[region]?.[range];
        if(metrics) {
            totalUnit += Number(metrics.unit) || 0;
            totalValue += Number(metrics.value) || 0;
            totalArea += Number(metrics.area) || 0;
        }
    }

    if (field === 'unit') return totalUnit;
    if (field === 'value') return totalValue;
    if (field === 'area') return totalArea;
    if (field === 'price_per_sqm') {
        return totalArea > 0 ? (totalValue / totalArea) : 0; // ⭐️ ใช้ / ธรรมดา
    }
    return 0;
}

// (6.A.5) [รวมทั้งหมด] คำนวณยอดรวม Grand Total
function getSummaryGrandTotal(field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;

    for (const region of regions.value) { 
        for (const range of priceRanges) {
            const metrics = rawData.value?.[region]?.[range];
             if(metrics) {
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
        return totalArea > 0 ? (totalValue / totalArea) : 0; // ⭐️ ใช้ / ธรรมดา
    }
    return 0;
}


// ⭐️ (6.B) Helpers สำหรับตาราง "รายเดือน" (ใช้ detailedTableData) ⭐️
// =============================================================

// ⭐️ (6.B.1) [ใหม่] Helper สำหรับวนลูปตาราง
const displayedMonths = computed(() => {
    return allMonthItems.filter(m => selectedMonths.value.includes(m.value))
                        .sort((a, b) => a.value - b.value);
});

// ⭐️ (6.B.2) [ใหม่] ดึงค่าดิบ (ตัวเลข)
function getMonthlyNumericValue(month: number, region: string, range: string, field: 'unit' | 'value' | 'area'): number {
    // ⭐️ ใช้ detailedTableData
    return Number(detailedTableData.value?.[month]?.[region]?.[range]?.[field] || 0);
}

// ⭐️ (6.B.3) [ใหม่] คำนวณค่าสำหรับช่องในตาราง (รวม price_per_sqm)
function getMonthlyCalculatedMetrics(month: number, region: string, range: string): RegionMetrics {
    const unit = getMonthlyNumericValue(month, region, range, 'unit');
    const value = getMonthlyNumericValue(month, region, range, 'value');
    const area = getMonthlyNumericValue(month, region, range, 'area');
    const price_per_sqm = area > 0 ? (value / area) : 0; // ⭐️ ใช้ / ธรรมดา
    
    return { unit, value, area, price_per_sqm };
}

// ⭐️ (6.B.4) [ใหม่] [แถวแนวนอน] คำนวณยอดรวมของ 1 PriceRange (รวมทุก Region)
function getMonthlyHorizontalTotal(month: number, range: string, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;

    // ⭐️ ใช้ regions.value (จาก "สรุป" เพื่อให้คอลัมน์ตรงกัน)
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

// ⭐️ (6.B.5) [ใหม่] [คอลัมน์แนวตั้ง] คำนวณยอดรวมของ 1 Region (รวมทุก PriceRange)
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

// ⭐️ (6.B.6) [ใหม่] [รวมทั้งหมด] คำนวณยอดรวม Grand Total
function getMonthlyGrandTotal(month: number, field: keyof RegionMetrics): number {
    let totalUnit = 0;
    let totalValue = 0;
    let totalArea = 0;

    // ⭐️ ใช้ regions.value (จาก "สรุป" เพื่อให้คอลัมน์ตรงกัน)
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


// ⭐️ [แก้ไขตามคำขอ]
function formatValue(value: number, field: keyof RegionMetrics): string {
    if (field === 'unit') {
        // ⭐️ 'จำนวนหลัง' (unit) เท่านั้นที่ไม่ต้องมีทศนิยม (ปัดเศษ)
        return value.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
    
    // ⭐️ ที่เหลือ (value, area, price_per_sqm) แสดงทศนิยม 2 ตำแหน่ง
    return value.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// ⭐️ (6.D) [ใหม่] Helpers สำหรับ MoM% ⭐️
// ===================================

// ⭐️ (6.D.1) [ใหม่] คำนวณ %
function calculateMoMPercent(current: number, prior: number): number | null {
    if (prior === 0) {
        return current > 0 ? 100 : 0; // ถ้าของเดิมเป็น 0 แล้วมีของใหม่ = 100%
    }
    const percent = ((current - prior) / prior) * 100;
    if (!isFinite(percent)) {
        return null;
    }
    return percent;
}

// ⭐️ (6.D.2) [ใหม่] จัดรูปแบบ %
function getMoMFormatted(month: number, region: string, range: string, field: keyof RegionMetrics): string {
    const priorMonth = month - 1;
    
    // ⭐️ ตรวจสอบว่ามีข้อมูลเดือนก่อนหน้า (ที่ถูกดึงมา) หรือไม่
    if (!detailedTableData.value[priorMonth]) {
        return ""; // ไม่มีข้อมูลเทียบ
    }
    
    // ⭐️ (ถ้าต้องการเทียบข้ามปี ตอนเลือก ม.ค. ต้องแก้ logic ตรงนี้)
    // (ปัจจุบัน: ม.ค. จะไม่แสดง MoM%)

    const currentValue = getMonthlyCalculatedMetrics(month, region, range)[field];
    const priorValue = getMonthlyCalculatedMetrics(priorMonth, region, range)[field];

    const percent = calculateMoMPercent(currentValue, priorValue);

    if (percent === null) {
        return "";
    }
    
    const percentStr = percent.toFixed(0);
    
    if (percent > 0) {
        return `<span class="mom-percent text-success">(+${percentStr}%)</span>`;
    } else if (percent < 0) {
        return `<span class="mom-percent text-error">(${percentStr}%)</span>`;
    } else {
        return ""; // ⭐️ [แก้ไข] ไม่แสดง 0%
    }
}

// ⭐️ (6.D.3) [ใหม่] จัดรูปแบบ % (สำหรับแถว Total แนวนอน)
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
    return ""; // ⭐️ [แก้ไข] ไม่แสดง 0%
}

// ⭐️ (6.D.4) [ใหม่] จัดรูปแบบ % (สำหรับแถว Total แนวตั้ง)
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
    return ""; // ⭐️ [แก้ไข] ไม่แสดง 0%
}

// ⭐️ (6.D.5) [ใหม่] จัดรูปแบบ % (สำหรับแถว Grand Total)
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
    return ""; // ⭐️ [แก้ไข] ไม่แสดง 0%
}


// --- (7) Computed Data สำหรับ Graph และ Cards ---

// (7.1) ข้อมูลสำหรับ Polar Area Chart (คำนวณยอดรวม 'value' ของแต่ละ Region)
const polarAreaSeries = computed(() => {
    // ⭐️ ใช้ Helper สรุป (A.4)
    const series = regions.value.map(region => getSummaryVerticalTotal(region, 'value')); 
    const totalSum = series.reduce((a, b) => a + b, 0);
    return totalSum > 0 ? series : [];
});

const polarAreaOptions = computed(() => ({
    chart: { type: 'polarArea', fontFamily: 'inherit', foreColor: '#6c757d' },
    labels: regions.value, // ⭐️ ใช้ .value เพื่อให้ดึงค่าล่าสุดมาใส่ใน labels
    legend: { position: 'bottom', horizontalAlign: 'center' },
    stroke: { colors: ['#fff'] },
    fill: { opacity: 0.8 },
    responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }],
    tooltip: { theme: 'dark', y: { formatter: (val: number) => val.toLocaleString('th-TH') + ' บาท' } },
    dataLabels: {
        enabled: true,
        formatter: (val: number, opts: any) => {
            let percentageText = '0.00%';
            if (!isNaN(val)) percentageText = (Number(val) || 0).toFixed(2) + '%';
            
            const regionKey = regions.value[opts.dataPointIndex]; 
            if (!regionKey) return percentageText;

            const rawValue = getSummaryVerticalTotal(regionKey, 'value'); // ⭐️ ใช้ Helper สรุป (A.4)
            
            const rawValueText = (Number(rawValue) || 0).toLocaleString('th-TH');
            return [percentageText, `(${rawValueText})`];
        },
        style: { fontSize: '10px' },
        dropShadow: { enabled: false }
    },
    noData: { text: 'ไม่พบข้อมูลสำหรับช่วงที่เลือก', align: 'center', verticalAlign: 'middle', offsetY: 0, style: { color: '#6c757d', fontSize: '14px', fontFamily: 'inherit' } },
}));

// (7.2) ⭐️ [ปรับปรุง] ข้อมูลการ์ดสรุป
// (7.2) ⭐️ [ปรับปรุง] ข้อมูลการ์ดสรุป (แก้ไขตามที่คุณต้องการ)
const summaryCardData = computed(() => {
    
    // ⭐️ [ใหม่] ตรวจสอบว่าแท็บที่เลือกเป็นรายเดือนหรือไม่
    // (ถ้า activeTab ไม่ใช่ 'summary' และเป็นตัวเลขเดือน)
    if (activeTab.value !== 'summary' && typeof activeTab.value === 'number') {
        const currentMonth = activeTab.value;
        
        // ⭐️ ถ้าเป็นแท็บรายเดือน ให้ใช้ข้อมูลสรุปของเดือนนั้น
        return {
            unit: getMonthlyGrandTotal(currentMonth, 'unit'),
            value: getMonthlyGrandTotal(currentMonth, 'value'),
            area: getMonthlyGrandTotal(currentMonth, 'area'),
            price_per_sqm: getMonthlyGrandTotal(currentMonth, 'price_per_sqm')
        };
    }

    // ⭐️ ถ้าเป็นแท็บ "สรุปภาพรวม" (default)
    // ⭐️ ให้ใช้ข้อมูลสรุปของ "ทุกเดือนที่เลือก" (จาก rawData)
    return {
        unit: getSummaryGrandTotal('unit'),
        value: getSummaryGrandTotal('value'),
        area: getSummaryGrandTotal('area'),
        price_per_sqm: getSummaryGrandTotal('price_per_sqm')
    };
});

// ⭐️ [ใหม่] (7.3) Computed สำหรับ MoM% ของ Summary Cards
// ⭐️ [ใหม่] (7.3) Computed สำหรับ MoM% ของ Summary Cards (แก้ไขตามที่คุณต้องการ)
// ⭐️ [ใหม่] (7.3) Computed สำหรับ MoM% ของ Summary Cards (แก้ไขตามที่คุณต้องการ)
const summaryCardMoMData = computed(() => {
    
    // ⭐️ [ใหม่] MoM% จะแสดงก็ต่อเมื่อ "แท็บรายเดือน" ถูกเลือก
    // (ถ้าเป็นแท็บ 'summary' หรือค่า activeTab ไม่ใช่ตัวเลข ให้ซ่อน MoM%)
    if (activeTab.value === 'summary' || typeof activeTab.value !== 'number') {
        return { unit: null, value: null, area: null, price_per_sqm: null };
    }

    // ⭐️ ถ้าเป็นแท็บรายเดือน ให้ใช้ activeTab.value เป็นเดือนปัจจุบัน
    const currentMonth = activeTab.value;
    const priorMonth = currentMonth - 1;

    // ตรวจสอบว่ามีข้อมูลเดือนก่อนหน้าหรือไม่ (และไม่คำนวณข้ามปี)
    if (priorMonth <= 0 || !detailedTableData.value[priorMonth]) {
        return { unit: null, value: null, area: null, price_per_sqm: null };
    }

    const momValues: Record<string, string | null> = {};

    // ⭐️ วนลูป dataTypes (unit, value, area, price_per_sqm)
    for (const field of dataTypes) {
        // ⭐️ ใช้ Helper (6.B.6) ที่มีอยู่แล้ว
        const currentValue = getMonthlyGrandTotal(currentMonth, field);
        const priorValue = getMonthlyGrandTotal(priorMonth, field);
        
        // ⭐️ ใช้ Helper (6.D.1) ที่มีอยู่แล้ว
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
                 // ⭐️ [แก้ไข] ถ้าเป็น 0% ให้ไม่แสดง (null)
                momValues[field] = null;
            }
        }
    }

    return momValues as Record<keyof RegionMetrics, string | null>;
});


// --- (8) Export Excel ---
const exportToExcel = async () => {
    const workbook = new ExcelJS.Workbook();
    
    // ⭐️ [ปรับปรุง] Helper สำหรับสร้างตารางใน Excel
    const createSheet = (title: string, dataProvider: (region: string, range: string, field: keyof RegionMetrics) => number, totalHProvider: (range: string, field: keyof RegionMetrics) => number, totalVProvider: (region: string, field: keyof RegionMetrics) => number, grandTotalProvider: (field: keyof RegionMetrics) => number) => {
        const worksheet = workbook.addWorksheet(title);

        worksheet.addRow([`สรุปข้อมูลรายงาน ${title} ${filterSubtitle.value}`]);
        worksheet.getRow(1).font = { bold: true, size: 14 };
        worksheet.getRow(1).alignment = { horizontal: 'center' };
        worksheet.mergeCells(1, 1, 1, regions.value.length + 2);

        const headerRowValues = ['(มูลค่า / ภูมิภาค)', ...regions.value, 'รวม (แนวนอน)'];
        const headerRow = worksheet.addRow(headerRowValues);
        headerRow.font = { bold: true };
        headerRow.alignment = { horizontal: 'center' };
        headerRow.eachCell((cell: Cell) => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'D3D3D3' } };
            cell.border = { top: { style: 'thin' as BorderStyle }, left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle } };
        });

        priceRanges.forEach((range) => {
            worksheet.addRow([range]).font = { bold: true, color: { argb: '725AF2' } };
            dataTypes.forEach(field => {
                const rowValues: (string | number)[] = [`  ${dataTypeLabels[field]}`];
                regions.value.forEach(region => {
                    rowValues.push(dataProvider(region, range, field));
                });
                rowValues.push(totalHProvider(range, field));
                
                const dataRow = worksheet.addRow(rowValues);
                dataRow.eachCell((cell: Cell, colNumber) => {
                    if (colNumber > 1) { 
                        // ⭐️ [แก้ไข]
                        cell.numFmt = (field === 'price_per_sqm') ? '#,##0.00' : '#,##0';
                        cell.alignment = { horizontal: 'right' };
                    }
                    cell.border = { top: { style: 'thin' as BorderStyle }, left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle } };
                });
            });
        });

        worksheet.addRow(['รวม (แนวตั้ง)']).font = { bold: true, color: { argb: 'F8285A' } };
        dataTypes.forEach(field => {
            const rowValues: (string | number)[] = [`  ${dataTypeLabels[field]}`];
            regions.value.forEach(region => {
                rowValues.push(totalVProvider(region, field));
            });
            rowValues.push(grandTotalProvider(field));
            
            const totalRow = worksheet.addRow(rowValues);
            totalRow.font = { bold: true };
            totalRow.eachCell((cell: Cell, colNumber) => {
                if (colNumber > 1) {
                     // ⭐️ [แก้ไข]
                    cell.numFmt = (field === 'price_per_sqm') ? '#,##0.00' : '#,##0';
                    cell.alignment = { horizontal: 'right' };
                }
                cell.border = { top: { style: 'thin' as BorderStyle }, left: { style: 'thin' as BorderStyle }, right: { style: 'thin' as BorderStyle }, bottom: { style: 'thin' as BorderStyle } };
            });
        });

        worksheet.columns = [
            { key: 'label', width: 25 },
            ...regions.value.map(r => ({ key: r, width: 20 })),
            { key: 'total', width: 20 }
        ];
    };
    
    // ⭐️ [ใหม่] สร้าง Sheet สำหรับ "สรุปยอดรวม"
    createSheet(
        'สรุปยอดรวม',
        (region, range, field) => getSummaryCalculatedMetrics(region, range)[field],
        (range, field) => getSummaryHorizontalTotal(range, field),
        (region, field) => getSummaryVerticalTotal(region, field),
        (field) => getSummaryGrandTotal(field)
    );

    // ⭐️ [ใหม่] วนลูปสร้าง Sheet สำหรับ "รายเดือน"
    for (const month of displayedMonths.value) {
         createSheet(
            `เดือน ${month.title}`,
            (region, range, field) => getMonthlyCalculatedMetrics(month.value, region, range)[field],
            (range, field) => getMonthlyHorizontalTotal(month.value, range, field),
            (region, field) => getMonthlyVerticalTotal(month.value, region, field),
            (field) => getMonthlyGrandTotal(month.value, field)
        );
    }

    // Write file
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheet.sheet' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Region_PriceRange_Report_${selectedYear.value}.xlsx`;
    a.click();
    window.URL.revokeObjectURL(url);
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

function getSummaryGrandTotalContributionPercent(field: keyof RegionMetrics): string {
    
     if (field === 'price_per_sqm') {
        return "";
    }
    
    const grandTotal = getSummaryGrandTotal(field);
    if (grandTotal === 0) {
        return "";
    }
    return `<span class="contribution-percent" style="font-size: 10px; color: #f8285a; font-weight: 600; margin-left: 4px; white-space: nowrap;">(100.0%)</span>`;
}



</script>

<template>
    <v-row>

        
        <v-col cols="12" sm="12" lg="12">
            <div class="mt-3 mb-6">
                <div class="d-flex justify-space-between">
                    <div class="d-flex py-0 align-center">
                        <div>
                            <h3 class="text-h5 card-title">รายงานยอดเซ็นสัญญา (มูลค่า x ภูมิภาค)</h3>
                            <ul class="v-breadcrumbs v-breadcrumbs--density-default text-subtitle-1 textSecondary pa-0 ml-n1">
                                <li class="v-breadcrumbs-item" text="Home">
                                    <a class="v-breadcrumbs-item--link" href="#"><p>หน้าแรก</p></a>
                                </li>
                                <li class="v-breadcrumbs-divider">
                                    <i
                                        class="mdi-chevron-right mdi v-icon notranslate v-theme--BLUE_THEME"
                                        aria-hidden="true"
                                        style="font-size: 15px; height: 15px; width: 15px"
                                    ></i>
                                </li>
                                <li class="v-breadcrumbs-item" text="Dashboard">
                                    <a class="v-breadcrumbs-item--link" href="#"><p>รายงานยอดเซ็นสัญญา (มูลค่า x ภูมิภาค)</p></a>
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
                            <v-select
                                v-model="selectedYear"
                                :items="yearOptions"
                                label="ปี (พ.ศ.)"
                                density="compact"
                                variant="outlined"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" sm="4" md="4">
                            <v-select
                                v-model="selectedQuarter"
                                :items="quarterOptions"
                                item-title="title"
                                item-value="value"
                                label="ไตรมาส"
                                density="compact"
                                variant="outlined"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" sm="4" md="4">
                            <v-select
                                v-model="selectedMonths"
                                :items="monthOptions"
                                item-title="title"
                                item-value="value"
                                label="เดือน (เลือกได้หลายเดือน)"
                                multiple
                                chips
                                closable-chips
                                density="compact"
                                variant="outlined"
                                hide-details
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-col>

          <v-col cols="12" sm="12" lg="12">
            <div class="v-row">
                <div
                    v-for="field in dataTypes"
                    :key="field"
                    class="v-col-sm-6 v-col-lg-3 v-col-12 py-0 mb-0"
                >
                    <div class="v-card v-theme--BLUE_THEME v-card--density-default elevation-10 rounded-md v-card--variant-elevated">
                        <div class="v-card-text pa-5">
                            <div class="d-flex align-center ga-4">
                                <button
                                    type="button"
                                    class="v-btn v-btn--elevated v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                    :class="{
                                        'bg-primary': field === 'unit',
                                        'bg-secondary': field === 'value',
                                        'bg-error': field === 'area',
                                        'bg-warning': field === 'price_per_sqm'
                                    }"
                                    dark
                                >
                                    <!-- (Icons SVG) -->
                                    <svg v-if="field === 'unit'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" /><path stroke-linecap="round" d="M12 15v3" /></g></svg>
                                    <svg v-else-if="field === 'value'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" /><path stroke-linecap="round" d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" /></g></svg>
                                    <svg v-else-if="field === 'area'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" /><path stroke-linejoin="round" d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" /></g></svg>
                                    <svg v-else-if="field === 'price_per_sqm'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808-1.123C21.007 7.109 22 7.506 22 8s-.993-.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" /><path d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" /><path d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" /></g></svg>
                                </button>
                                <div class="">
                                    <div class="d-flex align-end ga-2">
                                        <h2 class="text-h4" style="line-height: 1.1;">
                                            <!-- ⭐️ [แก้ไข] ใช้ formatValue สำหรับการ์ดด้วย -->
                                            {{ formatValue(summaryCardData[field], field) }}
                                        </h2>
                                        
                                        <!-- ⭐️ [ใหม่] ส่วนแสดง MoM% ⭐️ -->
                                        <span style="font-size: 10px;"
                                            v-if="summaryCardMoMData[field]" 
                                            class="mom-card" 
                                            :class="{
                                                'text-success': summaryCardMoMData[field] && summaryCardMoMData[field].includes('+'),
                                                'text-error': summaryCardMoMData[field] && summaryCardMoMData[field].includes('-')
                                            }"
                                        >
                                            {{ summaryCardMoMData[field] }}
                                        </span>
                                        <!-- ⭐️ [ใหม่] จบส่วน MoM% ⭐️ -->
                                    </div>
                                    
                                    <p class="textSecondary mt-1 text-15">{{ dataTypeLabels[field] }} (รวม)</p>
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
                    <div class="v-row">
                        <div class="v-col-md-8 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">สัดส่วนมูลค่ารวม (Total Value) ตามภูมิภาค</h3>
                                    <h5 class="card-subtitle" style="text-align: left">{{ filterSubtitle }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5">
                        <apexchart type="polarArea" :options="polarAreaOptions" :series="polarAreaSeries" height="400" />
                    </div>
                </v-card-text>
            </VCard>
        </v-col>

        <!-- ⭐️ [ปรับปรุง] เริ่มตารางใหม่ (ใช้ Tabs) ⭐️ -->
        <v-col cols="12" sm="12" lg="12">
            <v-card elevation="10">
                 <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <h3 class="card-title mb-1">ตารางสรุป (แถว=มูลค่า, คอลัมน์=ภูมิภาค)</h3>
                        <h5 class="card-subtitle" style="text-align: left">{{ filterSubtitle }}</h5>
                    </div>
                    <v-btn class="text-primary v-btn--size-small" @click="exportToExcel">
                        <div class="text-none font-weight-regular muted">Export to Excel</div>
                    </v-btn>
                </v-card-title>
                <v-card-text>
                                <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                                    <div class="v-table__wrapper" style="overflow-x: auto">
                                        <table>
                                            <thead style="background-color: #f5f5f5">
                                                <tr>
                                                    <th class="text-h6" style="min-width: 80px; text-align: left;"></th>
                                                    <th
                                                        v-for="month_item in displayedMonths"
                                                        :key="month_item.value"
                                                        class="text-p"
                                                        style="font-size: 13px; text-align: center"
                                                    >
                                                        {{ month_item.short }}
                                                    </th>
                                                    <th
                                                        class="text-p"
                                                        style="font-size: 13px; font-weight: 600; background-color: #fff3e0; text-align: center"
                                                    >
                                                        รวม
                                                    </th>
                                                </tr>
                                            </thead>
                                            
                                            <tbody>
                                                <template v-for="region in regions" :key="region">
                                                    <tr class="month-item" style="background-color: #fcf8ff">
                                                        <td style="text-align: left;">
                                                            <h6 class="text-p" style="font-size: 12px; font-weight: 600; color: #725af2">{{ region }}</h6>
                                                        </td>
                                                        <td :colspan="displayedMonths.length + 1"></td>
                                                    </tr>
                                                    
                                                    <tr class="month-item" v-for="field in dataTypes" :key="region + field">
                                                        <td style="text-align: left;">
                                                            <h6 class="text-p" style="font-size: 12px; font-weight: 400; padding-left: 15px">
                                                                {{ dataTypeLabels[field] }}
                                                            </h6>
                                                        </td>
                                                        
                                                        <!-- ⭐️ [แก้ไข 1/4] ปรับขนาดฟอนต์ และปรับโครงสร้าง h6/span -->
                                                        <td v-for="month_item in displayedMonths" :key="region + field + month_item.value" style="text-align: right; vertical-align: middle;">
                                                            <div>
                                                                <h6 class="text-p" style="font-size: 12px; font-weight: 400;">
                                                                    {{ formatValue(getMonthlyVerticalTotal(month_item.value, region, field), field) }}
                                                                </h6>
                                                                <span style="font-size: 9px !important;" v-html="getMoMFormatted_VerticalTotal(month_item.value, region, field)"></span>
                                                            </div>
                                                        </td>
                                                        
                                                        <!-- ⭐️ [แก้ไข 2/4] ปรับขนาดฟอนต์ และเพิ่ม wrapper -->
                                                        <td style="background-color: #fff3e0; text-align: right; vertical-align: middle;">
                                                            <div>
                                                                <h6 class="text-p" style="font-size: 12px; font-weight: 600">
                                                                    {{ formatValue(getRegionHorizontalTotal(region, field), field) }}
                                                                </h6>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                                
                                                <tr class="month-item" style="background-color: #fcf8ff;" v-for="field in dataTypes" :key="'total-' + field">
                                                    <td style="text-align: left;">
                                                        <h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #f8285a">
                                                            {{ dataTypeLabels[field] }} (รวม)
                                                        </h6>
                                                    </td>
                                                    
                                                    <!-- ⭐️ [แก้ไข 3/4] ปรับขนาดฟอนต์ และปรับโครงสร้าง h6/span -->
                                                    <td v-for="month_item in displayedMonths" :key="'total-' + month_item.value + field" style="text-align: right; vertical-align: middle;">
                                                        <div>
                                                            <h6 class="text-p" style="font-size: 12px; font-weight: 600; color: #f8285a">
                                                                {{ formatValue(getMonthlyGrandTotal(month_item.value, field), field) }}
                                                            </h6>
                                                            <span  style="font-size: 9px !important;" v-html="getMoMFormatted_GrandTotal(month_item.value, field)"></span>
                                                        </div>
                                                    </td>
                                                    
                                                    <!-- ⭐️ [แก้ไข 4/4] ปรับขนาดฟอนต์ และเพิ่ม wrapper -->
                                                    <td style="background-color: #fff3e0; text-align: right; vertical-align: middle;">
                                                         <div>
                                                            <h6 class="text-p" style="font-size: 12px; font-weight: 800; color: #f8285a">
                                                                {{ formatValue(getMonthlyTabGrandTotal(field), field) }}
                                                            </h6>
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
       
        <!-- ⭐️ [ปรับปรุง] สิ้นสุดตารางใหม่ ⭐️ -->

      
    </v-row>
</template>

<style scoped>
/* (Style เหมือน house.vue) */
.text-subtitle-1 {
    font-size: 14px;
}
.text-h6 {
    font-size: 18px;
}
/* ⭐️ [ปรับปรุง] แก้ไข Style ให้เข้ากับตารางใหม่ */
th.text-left, td.text-left {
    text-align: left !important;
    padding-left: 16px !important;
}
th.text-right, td.text-right {
    text-align: right !important;
    padding-right: 16px !important;
}
/* ⭐️ [เพิ่มกลับมา] */
.month-item td,
.month-item th {
    padding: 8px !important;
    border-bottom: 1px solid #eee;
}

/* ⭐️ [โค้ดใหม่] สำหรับปรับแต่งสี Tab ⭐️ */

/* (1) ทำให้พื้นหลังของแถบ Tab ทั้งหมดเป็นสีขาว */
:deep(.v-slide-group__container) {
    background-color: #FFFFFF;
    border-radius: 5px;
}

/* (2) สไตล์ของ Tab (ปกติ) */
:deep(.v-tab) {
    background-color: #FFFFFF; /* พื้นหลังสีขาว */
    color: #000000 !important; /* ข้อความสีดำ */
    opacity: 0.8; /* (ทำให้แท็บที่ไม่ active ดูจางลงเล็กน้อย) */
     border-radius: 5px;
}

/* (3) สไตล์เมื่อ Hover (เอาเมาส์ชี้) */
:deep(.v-tab:hover) {
    background-color: #2196F3 !important; /* พื้นหลังสีน้ำเงิน (Vuetify's Primary) */
    color: #FFFFFF !important; /* ข้อความสีขาว */
    opacity: 1;
     border-radius: 5px;
}

/* (4) สไตล์เมื่อ Tab ถูกเลือก (Active) */
:deep(.v-tab--selected) {
    background-color: #2196F3 !important; /* พื้นหลังสีน้ำเงิน */
    color: #FFFFFF !important; /* ข้อความสีขาว */
    opacity: 1;
     border-radius: 5px;
}

/* ⭐️ [ใหม่] สไตล์สำหรับ MoM% ⭐️ */
.mom-percent {
    font-size: 5px;
    margin-left: 4px;
    white-space: nowrap;
}
.text-success {
    color: #28a745; /* สีเขียว */
}
.text-error {
    color: #ed0b22; /* สีแดง */
}

/* ⭐️ [ใหม่] สไตล์สำหรับ MoM% บนการ์ด ⭐️ */
.mom-card {
    font-size: 1.1rem; /* ปรับขนาดตามความเหมาะสม */
    font-weight: 600;
    margin-bottom: 5px; /* จัดตำแหน่งให้อยู่ข้าง H2 */
    white-space: nowrap;
}

/* ⭐️ [แก้ไข] เพิ่ม vertical-align 
   เพื่อให้ <span> ที่ถูกย้ายออกมา
   ยังอยู่กึ่งกลางแนวตั้ง */
.month-item td {
    vertical-align: middle;
}

</style>