<script setup lang="ts">
// (!!! เพิ่ม: computed ถูก import แล้ว) ---
import { ref, onMounted, watch, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

// --- (!!! เพิ่ม: ส่วนตัวแปรสำหรับ Notification !!!) ---
const statusMessage = ref(''); // สถานะการกรอกข้อมูล
const contractStatusMessage = ref(''); // (!!! ใหม่ !!!) สถานะการกรอกสัญญา
const fetchErrorUserStatus = ref('');
const fetchErrorContractStatus = ref(''); // (!!! ใหม่ !!!)

// (!!! เพิ่ม: ดึงข้อมูล user จาก localStorage !!!) ---
const userId = localStorage.getItem('user_id');
const userRole = ref(localStorage.getItem('user_role') || 'user');

const isAdmin = computed(() => userRole.value === 'admin' || userRole.value === 'master');

// --- (!!! เพิ่ม: ส่วนคำนวณวันที่ Notification !!!) ---
const months = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
];
const currentDate = new Date();
const currentYear = currentDate.getFullYear() + 543; // ปีพุทธศักราช
const currentMonth = currentDate.getMonth(); // เดือนปัจจุบัน (0-11)
const currentDay = currentDate.getDate(); // วันที่ปัจจุบัน (1-31)
const currentMonthName = months[currentMonth];

const nextMonthDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
const nextMonthName = months[nextMonthDate.getMonth()];
const nextMonthYearDisplay = nextMonthDate.getFullYear() + 543;

const deadlineDay = 10;

const remainingDays = computed(() => {
    if (currentDay > deadlineDay) {
        return 0;
    }
    return deadlineDay - currentDay;
});
// --- (!!! สิ้นสุดส่วนที่เพิ่ม: วันที่ !!!) ---


// --- (!!! เพิ่ม: Computed Property สำหรับ Notification ข้อมูล !!!) ---
const userNotification = computed(() => {
    // แสดงเฉพาะสำหรับบทบาท 'user'
    if (userRole.value !== 'user') {
        return { message: '', type: '', title: '' };
    }

    // 1. Submitted
    if (statusMessage.value === 'กรอกข้อมูลเรียบร้อย') {
        return {
            message: `คุณได้ทำการกรอกข้อมูลประจำเดือน ${currentMonthName} ${currentYear} เรียบร้อยแล้ว`,
            type: 'success',
            title: 'สถานะการกรอกข้อมูล'
        };
    }

    // 2. Not Submitted (ก่อนหรือตรงกับวันที่ 10)
    if (currentDay <= deadlineDay) {
        const days = remainingDays.value;
        let message = '';
        let type = 'warning';
        if (days > 0) {
            message = `เหลืออีก ${days} วัน ในการกรอกข้อมูล ก่อนวันที่ ${deadlineDay} ${currentMonthName} ${currentYear}`;
            if (days <= 2) {
                type = 'error';
            }
        } else { // วันที่ 10 พอดี
            message = `ถึงกำหนดส่งข้อมูลวันนี้! กรุณากรอกข้อมูลก่อนสิ้นสุดวันที่ ${deadlineDay} ${currentMonthName} ${currentYear}`;
            type = 'error';
        }
        return { message: message, type: type as 'warning' | 'error', title: 'กรุณากรอกข้อมูล' };
    }

    // 3. Not Submitted (หลังวันที่ 10)
    if (currentDay > deadlineDay) {
        const message = `เดือนนี้คุณไม่ได้กรอกข้อมูล กรุณายกยอดไปกรอกในเดือนถัดไป (${nextMonthName} ${nextMonthYearDisplay})`;
        return { message: message, type: 'error', title: 'การกรอกข้อมูลล่าช้า' };
    }

    // Fallback/Error
    if (fetchErrorUserStatus.value) {
        return { message: `ไม่สามารถตรวจสอบสถานะได้: ${fetchErrorUserStatus.value}`, type: 'error', title: 'ข้อผิดพลาด' };
    }
    return { message: '', type: '', title: '' }; // Default
});

// --- (!!! เพิ่ม: Computed Property สำหรับ Notification สัญญา !!!) ---
const contractNotification = computed(() => {
    // แสดงเฉพาะสำหรับบทบาท 'user'
    if (userRole.value !== 'user') {
        return { message: '', type: '', title: '' };
    }

    // 1. Submitted (สมมติว่าสถานะคือ 'กรอกสัญญาเรียบร้อย')
    // *** คุณต้องปรับ 'กรอกสัญญาเรียบร้อย' ให้ตรงกับค่าที่ Backend ส่งมา ***
    if (contractStatusMessage.value === 'กรอกสัญญาเรียบร้อย') {
        return {
            message: `คุณได้ทำการกรอกข้อมูลสัญญาประจำเดือน ${currentMonthName} ${currentYear} เรียบร้อยแล้ว`,
            type: 'success',
            title: 'สถานะการกรอกสัญญา'
        };
    }

    // 2. Not Submitted (ก่อนหรือตรงกับวันที่ 10)
    if (currentDay <= deadlineDay) {
        const days = remainingDays.value;
        let message = '';
        let type = 'warning';
        if (days > 0) {
            message = `เหลืออีก ${days} วัน ในการกรอกข้อมูลสัญญา ก่อนวันที่ ${deadlineDay} ${currentMonthName} ${currentYear}`;
            if (days <= 2) {
                type = 'error';
            }
        } else { // วันที่ 10 พอดี
            message = `ถึงกำหนดส่งข้อมูลสัญญาวันนี้! กรุณากรอกข้อมูลสัญญาก่อนสิ้นสุดวันที่ ${deadlineDay} ${currentMonthName} ${currentYear}`;
            type = 'error';
        }
        return { message: message, type: type as 'warning' | 'error', title: 'กรุณากรอกข้อมูลสัญญา' };
    }

    // 3. Not Submitted (หลังวันที่ 10)
    if (currentDay > deadlineDay) {
        const message = `เดือนนี้คุณไม่ได้กรอกข้อมูลสัญญา กรุณายกยอดไปกรอกในเดือนถัดไป (${nextMonthName} ${nextMonthYearDisplay})`;
        return { message: message, type: 'error', title: 'การกรอกสัญญาล่าช้า' };
    }

    // Fallback/Error
    if (fetchErrorContractStatus.value) {
        return { message: `ไม่สามารถตรวจสอบสถานะสัญญาได้: ${fetchErrorContractStatus.value}`, type: 'error', title: 'ข้อผิดพลาด' };
    }
    return { message: '', type: '', title: '' }; // Default
});
// --- (!!! สิ้นสุดส่วนที่เพิ่ม: Computed Notifications !!!) ---


// (--- โค้ดเดิมของคุณ ---)
const jsDate = new Date();
const currentJsYear = jsDate.getFullYear();
const currentJsMonth = jsDate.getMonth() + 1;
// ... (โค้ดเดิมของคุณทั้งหมด) ...
const allMonthItems = [
    { title: 'มกราคม', value: 1 }, { title: 'กุมภาพันธ์', value: 2 },
    { title: 'มีนาคม', value: 3 }, { title: 'เมษายน', value: 4 },
    { title: 'พฤษภาคม', value: 5 }, { title: 'มิถุนายน', value: 6 },
    { title: 'กรกฎาคม', value: 7 }, { title: 'สิงหาคม', value: 8 },
    { title: 'กันยายน', value: 9 }, { title: 'ตุลาคม', value: 10 },
    { title: 'พฤศจิกายน', value: 11 }, { title: 'ธันวาคม', value: 12 }
];
const selectedYear = ref(currentJsYear + 543);
const selectedQuarter = ref('all');
const selectedMonths = ref<number[]>([]);
const yearOptions = ref(
    Array.from({ length: 2 }, (_, i) => currentJsYear + 543 - i)
);
const quarterOptions = ref([
    { title: 'ทุกไตรมาส / ทุกเดือน', value: 'all' },
    { title: 'ไตรมาส 1 (ม.ค. - มี.ค.)', value: 'Q1' },
    { title: 'ไตรมาส 2 (เม.ย. - มิ.ย.)', value: 'Q2' },
    { title: 'ไตรมาส 3 (ก.ค. - ก.ย.)', value: 'Q3' },
    { title: 'ไตรมาส 4 (ต.ค. - ธ.ค.)', value: 'Q4' }
]);

// (!!! อัปเดต: ลบ selectedPeriod ออก !!!)
// type Period = '1M' | '3M' | '6M' | 'YTD'; // ลบ
// const selectedPeriod = ref<Period>('YTD'); // ลบ

// (!!! อัปเดต: เพิ่ม isUpdatingFromMonths (เหมือน region.vue) !!!)
const isUpdatingFromMonths = ref(false);

// (!!! อัปเดต: เปลี่ยน monthOptions เป็น computed (เหมือน region.vue) !!!)
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


const loading = ref(false);
const summaryData = ref({ total_units: 0, total_value: 0, total_area: 0, value_per_sqm: 0 });
const monthlyChartLabels = ref<string[]>([]);
const monthlyUnitsData = ref<number[]>([]);
const monthlyValueData = ref<number[]>([]);
const monthlyAreaData = ref<number[]>([]);
const monthlyValuePerSqmData = ref<number[]>([]);
const loadingRegional = ref(false);
const regionalData = ref<any[]>([]);
type Metric = 'units' | 'value' | 'area' | 'valuePerSqm';
const activeMetric = ref<Metric>('value');
// (--- จบส่วนโค้ดเดิม ---)


// --- (!!! เพิ่ม: ฟังก์ชันดึงสถานะผู้ใช้ (ข้อมูลและสัญญา) !!!) ---
// (ฟังก์ชันนี้จะเรียก API ที่ต่างจาก fetchData หลักของคุณ)
const fetchUserStatus = async () => {
    if (!userId) {
        fetchErrorUserStatus.value = 'ไม่พบข้อมูลผู้ใช้';
        statusMessage.value = '';
        fetchErrorContractStatus.value = 'ไม่พบข้อมูลผู้ใช้';
        contractStatusMessage.value = '';
        return;
    }

    try {
        const payload = {
            user_id: userId,
            buddhist_year: currentYear.toString(),
            month_number: (currentMonth + 1).toString() // (1-12)
        };

        // (!!! เรียก API เดิมที่ใช้เช็คสถานะ !!!)
        const res = await fetch('https://uat.hba-sales.org/backend/data_and_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        console.log('API Response Data (Status):', data);

        if (data.error) {
            statusMessage.value = '';
            fetchErrorUserStatus.value = data.error;
            contractStatusMessage.value = '';
            fetchErrorContractStatus.value = data.error;
        } else {
            statusMessage.value = data.status || 'กรอกข้อมูลเรียบร้อย';
            fetchErrorUserStatus.value = '';
            // (!!! ใหม่: ดึงสถานะสัญญา (สมมติชื่อ field คือ 'contract_status') !!!)
            contractStatusMessage.value = data.contract_status || '';
            fetchErrorContractStatus.value = '';
        }
    } catch (err) {
        console.error('Error fetching user status:', err);
        fetchErrorUserStatus.value = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
        statusMessage.value = '';
        fetchErrorContractStatus.value = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
        contractStatusMessage.value = '';
    }
};
// --- (!!! สิ้นสุดส่วนที่เพิ่ม: fetchUserStatus !!!) ---


// --- (!!! อัปเดต !!!) 3. ฟังก์ชันหลักในการดึงข้อมูล (โค้ดเดิมของคุณ) ---
const fetchData = async () => {
    // (A. ส่วน Logic ตรวจสอบค่าว่าง (เหมือนเดิม))
    if (selectedMonths.value.length === 0 || !selectedYear.value) {
        summaryData.value = { total_units: 0, total_value: 0, total_area: 0, value_per_sqm: 0 };
        monthlyChartLabels.value = []; monthlyUnitsData.value = [];
        monthlyValueData.value = []; monthlyAreaData.value = [];
        monthlyValuePerSqmData.value = [];

        regionalData.value = [];
        return;
    }

    // (B. สั่ง loading ทั้งคู่)
    loading.value = true;
    loadingRegional.value = true;

    try {
        const yearAD = selectedYear.value - 543;

        // --- (!!! นี่คือส่วนที่แก้ไข) ---

        // 1. สร้าง payload เป็น Object (ยังไม่ stringify)
        const payload: any = {
            year: yearAD,
            months: selectedMonths.value.sort((a, b) => a - b),
            role: userRole.value
        };

        // 2. ตรวจสอบสิทธิ์ และ *เพิ่ม* user_id เข้าไปใน Object
        if (!isAdmin.value && userId) {
            payload.user_id = userId; // ถ้าไม่ใช่ Admin ให้ส่ง user_id ของตัวเอง
        }

        // 3. Stringify Object ที่สมบูรณ์แล้ว
        const bodyPayload = JSON.stringify(payload);
        
        // --- (!!! สิ้นสุดส่วนแก้ไข) ---


        // (C. สร้าง Promise สำหรับ API ทั้งสองตัว)
        const chartApiUrl = 'https://uat.hba-sales.org/backend/get_dashboard_data.php';
        const regionalApiUrl = 'https://uat.hba-sales.org/backend/get_regional_comparison.php';

        const fetchOptions = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: bodyPayload // (ตอนนี้ bodyPayload จะมี user_id ถ้าจำเป็น)
        };

        const chartPromise = fetch(chartApiUrl, fetchOptions).then(res => res.json());
        const regionalPromise = fetch(regionalApiUrl, fetchOptions).then(res => res.json());

        // (D. รอให้ทั้งคู่เสร็จสิ้น)
        const [chartResponse, regionalResponse] = await Promise.all([chartPromise, regionalPromise]);

        // (E. ประมวลผล Chart (เหมือนเดิม))
        if (chartResponse.status === 'success') {
            summaryData.value = chartResponse.data.summary;
            const monthly = chartResponse.data.monthly_data;
            monthlyChartLabels.value = monthly.labels;
            monthlyUnitsData.value = monthly.units;
            monthlyValueData.value = monthly.value;
            monthlyAreaData.value = monthly.area;
            monthlyValuePerSqmData.value = monthly.valuePerSqm;
        } else {
            console.error('Error fetching chart data:', chartResponse.message);
        }

        // (F. ประมวลผลตาราง (ใหม่))
        if (regionalResponse.status === 'success') {
            regionalData.value = regionalResponse.data;
        } else {
            console.error('Error fetching regional data:', regionalResponse.message);
        }

    } catch (error) {
        console.error('Fetch error:', error);
    } finally {
        // (G. ปิด loading ทั้งคู่)
        loading.value = false;
        loadingRegional.value = false;
    }
};

// (!!! อัปเดต: ลบฟังก์ชัน setPeriod ออก !!!)
// const setPeriod = (period: Period) => { ... }; // ลบ

// --- (!!! อัปเดต: Logic Filters & onMounted (ยกมาจาก region.vue) !!!) ---
watch(selectedQuarter, (newQuarter) => {
    if (isUpdatingFromMonths.value) {
        isUpdatingFromMonths.value = false;
        return;
    }
    // ดึงเดือนที่ "มีสิทธิ์" เลือกได้ในปีนั้นๆ
    const validMonthValues = monthOptions.value.map(m => m.value);
    const filterValidMonths = (months: number[]) => months.filter(m => validMonthValues.includes(m));

    if (newQuarter === 'all') updateToAllMonths();
    else if (newQuarter === 'Q1') selectedMonths.value = filterValidMonths([1, 2, 3]);
    else if (newQuarter === 'Q2') selectedMonths.value = filterValidMonths([4, 5, 6]);
    else if (newQuarter === 'Q3') selectedMonths.value = filterValidMonths([7, 8, 9]);
    else if (newQuarter === 'Q4') selectedMonths.value = filterValidMonths([10, 11, 12]);
});

watch(selectedYear, () => {
    // กรองเดือนที่เลือกไว้ ให้เหลือเฉพาะเดือนที่ "มี" ในปีใหม่
    const validMonths = monthOptions.value.map((m) => m.value);
    selectedMonths.value = selectedMonths.value.filter((m) => validMonths.includes(m));

    if (selectedQuarter.value === 'all') {
        updateToAllMonths(); 
    } else {
        // ถ้าเลือก Q1-Q4 ค้างไว้ ให้ re-trigger logic ของ 'watch(selectedQuarter)'
        // เพื่อเลือกเดือนของไตรมาสนั้นๆ "ในปีใหม่"
        const currentQuarter = selectedQuarter.value;
        selectedQuarter.value = ''; // เปลี่ยนค่าชั่วคราว
        selectedQuarter.value = currentQuarter; // เปลี่ยนกลับเพื่อ trigger watch
    }
});

watch(
    selectedMonths,
    (newMonths, oldMonths) => {
        
        const sortedMonths = [...newMonths].sort((a, b) => a - b).join(',');
        
        // ดึงเดือนที่ "มีสิทธิ์" เลือกได้ในปีนั้นๆ
        const validMonthValues = monthOptions.value.map(m => m.value);
        
        // สร้าง key ของไตรมาส (ที่ valid)
        const q1Months = [1, 2, 3].filter(m => validMonthValues.includes(m)).join(',');
        const q2Months = [4, 5, 6].filter(m => validMonthValues.includes(m)).join(',');
        const q3Months = [7, 8, 9].filter(m => validMonthValues.includes(m)).join(',');
        const q4Months = [10, 11, 12].filter(m => validMonthValues.includes(m)).join(',');

        // 1. ซิงค์ Dropdown 'ไตรมาส'
        if (sortedMonths === q1Months && q1Months.length > 0) selectedQuarter.value = 'Q1';
        else if (sortedMonths === q2Months && q2Months.length > 0) selectedQuarter.value = 'Q2';
        else if (sortedMonths === q3Months && q3Months.length > 0) selectedQuarter.value = 'Q3';
        else if (sortedMonths === q4Months && q4Months.length > 0) selectedQuarter.value = 'Q4';
        else {
            // 2. ถ้าไม่ตรงไตรมาส, เช็คว่าตรงกับ "YTD" (ทุกเดือน) หรือไม่
            const yearAD = selectedYear.value - 543;
            const allMonthsCurrentYear = allMonthItems.map((m) => m.value).slice(0, currentJsMonth).join(',');
            const allMonthsPastYear = allMonthItems.map((m) => m.value).join(',');

            if (sortedMonths === allMonthsCurrentYear || sortedMonths === allMonthsPastYear) {
                // ถ้าตรง YTD และ Dropdown ยังไม่เป็น 'all'
                if (selectedQuarter.value !== 'all') {
                    isUpdatingFromMonths.value = true; // ตั้ง Flag
                    selectedQuarter.value = 'all'; // สั่งให้ Dropdown เป็น 'all'
                }
            } else if (selectedQuarter.value !== 'all') {
                // 3. ถ้าไม่ตรงไตรมาส และไม่ตรง YTD (เช่น เลือกเอง [1, 5, 9])
                isUpdatingFromMonths.value = true; // ตั้ง Flag
                selectedQuarter.value = 'all'; // สั่งให้ Dropdown เป็น 'all'
            }
        }
        
        // 4. (สำคัญ!) เมื่อเดือนเปลี่ยน ให้ดึงข้อมูล
        fetchData();
    },
    { deep: true }
);

const updateToAllMonths = () => {
    const yearAD = selectedYear.value - 543;
    if (yearAD === currentJsYear) {
        selectedMonths.value = allMonthItems.map(m => m.value).filter(m => m <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        selectedMonths.value = [];
    } else {
        selectedMonths.value = allMonthItems.map(m => m.value);
    }
};

onMounted(() => {
    // (!!! อัปเดต: เปลี่ยนกลับไปใช้ updateToAllMonths (เหมือน region.vue) !!!)
    updateToAllMonths();
    fetchUserStatus(); 
});
// --- (!!! สิ้นสุดการอัปเดต Logic Filters !!!) ---


const formattedSummary = computed(() => ({
    units: summaryData.value.total_units.toLocaleString('th-TH') + ' หลัง',
    value: (summaryData.value.total_value / 1000000).toLocaleString('th-TH', { maximumFractionDigits: 2 }) + ' ล้าน',
    area: summaryData.value.total_area.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' ตร.ม.',
    valuePerSqm: summaryData.value.value_per_sqm.toLocaleString('th-TH', { maximumFractionDigits: 0 }) + ' / ตร.ม.'
}));

// (B) computed ใหม่: เช็คว่าโชว์ MoM ได้หรือไม่ (เหมือนเดิม)
const showMomColumn = computed(() => {
    return selectedMonths.value.length === 1;
});

// (C) Master List 7 ภูมิภาค (เหมือนเดิม)
const allRegionsMasterList = [
    'กรุงเทพปริมณฑล',
    'ภาคเหนือ',
    'ภาคตะวันออกเฉียงเหนือ',
    'ภาคกลาง',
    'ภาคตะวันออก',
    'ภาคใต้',
    'ภาคตะวันตก'
];

const buildTotalPayload = () => {
    const payload: any = {
        buddhist_year: selectedYear.value,
        role: userRole.value,
    };

    // 👇 --- นี่คือส่วนสำคัญ --- 👇
    if (!isAdmin.value && userId) {
        payload.user_id = userId;
    }
    // 👆 --- ----------------- 👆
    return payload;
};

// (ใน buildPreviousMonthPayload ก็มี Logic เดียวกัน)

// (!!! D. อัปเดต: computed สำหรับตารางใหม่ (รองรับ 4 Metrics) !!!)
const regionalTableData = computed(() => {

    // 1. สร้าง Map จากข้อมูล API
    const dataMap = new Map(regionalData.value.map(row => [row.region, row]));

    // 2. วนลูปจาก "Master List"
    return allRegionsMasterList.map(regionName => {

        const row = dataMap.get(regionName) || null;

        // 3. ถ้าไม่พบข้อมูล (row === null) -> คืนค่า 0
        if (!row) {
            return {
                region: regionName,
                current_period: 0, yoy_change: 0, mom_change: 0,
                cytd: 0, pytd: 0, ytd_change: 0
            };
        }

        // 4. (!!! อัปเดต !!!) ถ้าพบข้อมูล -> ดึงข้อมูลดิบทั้งหมด
        const raw = {
            cp_units: parseFloat(row.cp_units),
            cp_value: parseFloat(row.cp_value),
            cp_area: parseFloat(row.cp_area),

            pyp_units: parseFloat(row.pyp_units),
            pyp_value: parseFloat(row.pyp_value),
            pyp_area: parseFloat(row.pyp_area),

            cytd_units: parseFloat(row.cytd_units),
            cytd_value: parseFloat(row.cytd_value),
            cytd_area: parseFloat(row.cytd_area),

            pytd_units: parseFloat(row.pytd_units),
            pytd_value: parseFloat(row.pytd_value),
            pytd_area: parseFloat(row.pytd_area),

            // (mom_... ตอนนี้คือ 'เดือนก่อนหน้าเดือนล่าสุด')
            mom_units: parseFloat(row.mom_units),
            mom_value: parseFloat(row.mom_value),
            mom_area: parseFloat(row.mom_area),

            // (!!! ใหม่: ดึงข้อมูลเดือนล่าสุด (LSM) !!!)
            lsm_units: parseFloat(row.lsm_units),
            lsm_value: parseFloat(row.lsm_value),
            lsm_area: parseFloat(row.lsm_area)
        };

        // 5. (!!! อัปเดต !!!) เลือก metricData (แยก MoM ออกมา)
        let metricData; // (สำหรับ CP, PYP, CYTD, PYTD)
        let momMetricData; // (!!! ใหม่: สำหรับ MoM เท่านั้น !!!)

        if (activeMetric.value === 'units') {
            metricData = { cp: raw.cp_units, pyp: raw.pyp_units, cytd: raw.cytd_units, pytd: raw.pytd_units };
            // (!!! MoM เทียบ lsm (ล่าสุด) กับ mom (เดือนก่อน) !!!)
            momMetricData = { latest: raw.lsm_units, prev: raw.mom_units };

        } else if (activeMetric.value === 'area') {
            metricData = { cp: raw.cp_area, pyp: raw.pyp_area, cytd: raw.cytd_area, pytd: raw.pytd_area };
            momMetricData = { latest: raw.lsm_area, prev: raw.mom_area };

        } else if (activeMetric.value === 'valuePerSqm') {
            // (คำนวณ 'ยอดรวม')
            metricData = {
                cp: raw.cp_area > 0 ? (raw.cp_value / raw.cp_area) : 0,
                pyp: raw.pyp_area > 0 ? (raw.pyp_value / raw.pyp_area) : 0,
                cytd: raw.cytd_area > 0 ? (raw.cytd_value / raw.cytd_area) : 0,
                pytd: raw.pytd_area > 0 ? (raw.pytd_value / raw.pytd_area) : 0
            };
            // (!!! คำนวณ MoM V/Sqm แยก !!!)
            const lsm_vps = raw.lsm_area > 0 ? (raw.lsm_value / raw.lsm_area) : 0;
            const mom_vps = raw.mom_area > 0 ? (raw.mom_value / raw.mom_area) : 0;
            momMetricData = { latest: lsm_vps, prev: mom_vps };

        } else { // Default คือ 'value'
            metricData = { cp: raw.cp_value, pyp: raw.pyp_value, cytd: raw.cytd_value, pytd: raw.pytd_value };
            momMetricData = { latest: raw.lsm_value, prev: raw.mom_value };
        }

        // 6. (!!! อัปเดต !!!) คำนวณ %
        // (YoY และ YTD เหมือนเดิม - ใช้ metricData.cp)
        const yoy_change = (metricData.pyp > 0)
            ? ((metricData.cp - metricData.pyp) / metricData.pyp) * 100
            : (metricData.cp > 0 ? 100 : 0);

        const ytd_change = (metricData.pytd > 0)
            ? ((metricData.cytd - metricData.pytd) / metricData.pytd) * 100
            : (metricData.cytd > 0 ? 100 : 0);

        // (!!! MoM ใช้ตรรกะใหม่: latest vs prev !!!)
        const mom_change = (momMetricData.prev > 0)
            ? ((momMetricData.latest - momMetricData.prev) / momMetricData.prev) * 100
            : (momMetricData.latest > 0 ? 100 : 0);

        // 7. คืนค่า (ใช้ metricData.cp สำหรับ 'ยอดรวม')
        return {
            region: regionName,
            current_period: metricData.cp, // (คอลัมน์นี้ยังคงเป็น 'ยอดรวม' ถูกต้องแล้ว)
            yoy_change: yoy_change,
            mom_change: mom_change, // (คอลัมน์นี้คำนวณจาก (LSM vs Prev) ถูกต้องแล้ว)
            cytd: metricData.cytd,
            pytd: metricData.pytd,
            ytd_change: ytd_change
        };
    });
});

// (!!! E. อัปเดต: Headers สำหรับตารางใหม่ (รองรับ 4 Metrics) !!!)
const regionalTableHeaders = computed(() => {
    // (!!! ใหม่: เปลี่ยนชื่อ Metric ให้ถูกต้อง !!!)
    let metricName = 'มูลค่า (บาท)'; // Default
    if (activeMetric.value === 'units') metricName = 'จำนวน (หลัง)';
    else if (activeMetric.value === 'area') metricName = 'พื้นที่ (ตร.ม.)';
    else if (activeMetric.value === 'valuePerSqm') metricName = 'มูลค่า/ตร.ม. (บาท)';

    // (!!! ดึงปี พ.ศ. ปัจจุบันและปีที่แล้วมาใช้ !!!)
    const currentYearBE = selectedYear.value;     // เช่น 2568
    const previousYearBE = selectedYear.value - 1; // เช่น 2567

    const headers = [
        { title: 'ภูมิภาค', key: 'region', align: 'start', sortable: true, width: '25%' },
        // { title: `ยอดรวม (${metricName})`, key: 'current_period', align: 'end', sortable: true },

        { title: 'MoM %', key: 'mom_change', align: 'end', sortable: true },
        { title: 'YoY %', key: 'yoy_change', align: 'end', sortable: true },

        { title: `YTD ${currentYearBE} `, key: 'cytd', align: 'end', sortable: true },
        { title: `YTD ${previousYearBE} `, key: 'pytd', align: 'end', sortable: true },

        { title: 'YTD %', key: 'ytd_change', align: 'end', sortable: true },
    ] as const; // (!!! <-- เพิ่ม 'as const' ตรงนี้ครับ !!!)

    return headers;
});
// (!!! F. Helpers สำหรับตารางใหม่ (เหมือนเดิม) !!!)
const formatPercentage = (value: number) => {
    if (value === 0) return '0.0%';
    const prefix = value > 0 ? '+' : '';
    return `${prefix}${value.toFixed(1)}%`;
};

const getPercentageColor = (value: number) => {
    if (value > 0) return 'text-success';
    if (value < 0) return 'text-error';
    return 'text-grey';
};

// (!!! G. X-Axis Title (Dynamic) (เหมือนเดิม) !!!)
const xaxisTitleText = computed(() => {
    if (selectedQuarter.value !== 'all') {
        return 'เดือน';
    }
    const yearAD = selectedYear.value - 543;
    let totalMonthsInSelectedYear;
    if (yearAD === currentJsYear) totalMonthsInSelectedYear = currentJsMonth;
    else if (yearAD > currentJsYear) totalMonthsInSelectedYear = 0;
    else totalMonthsInSelectedYear = 12;

    if (selectedMonths.value.length === totalMonthsInSelectedYear || selectedMonths.value.length === 0) {
        return 'เดือน';
    }
    if (selectedMonths.value.length > 0) {
        return 'เดือน (ที่เลือก)';
    }
    return 'เดือน';
});

const tableKey = computed(() => {
    return `${activeMetric.value}-${showMomColumn.value}`;
});

// (!!! H. Chart Options (อัปเดต: กราฟผสม 2 แกน Y) !!!)
const chartOptions = computed(() => {
    let yAxisTitle = '';
    let barColor = '#43ced7';

    if (activeMetric.value === 'units') {
        yAxisTitle = 'จำนวน (หลัง)';
        barColor = '#1b84ff'; 
    }
    else if (activeMetric.value === 'area') {
        yAxisTitle = 'พื้นที่ (ตร.ม.)';
        barColor = '#f8285a';
    }
    else if (activeMetric.value === 'valuePerSqm') {
        yAxisTitle = 'มูลค่า/ตร.ม. (บาท)';
        barColor = '#f6c000';
    }
    else if (activeMetric.value === 'value') {
        yAxisTitle = 'มูลค่า (บาท)';
        barColor = '#43ced7';
    }

    return {
        chart: {
            type: 'line', 
            height: 350,
            stacked: false,
            fontFamily: 'inherit',
            foreColor: '#6c757d',
            toolbar: {
                show: true,
                tools: { download: true }
            }
        },
        
        colors: [barColor, '#E53935'], 

        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '70%',
                dataLabels: {
                    position: 'top',
                },
            },
            line: {
                curve: 'smooth',
            }
        },

        dataLabels: {
            enabled: true,
            enabledOnSeries: [0], 
            offsetY: -13,
            style: {
                fontSize: '10px',
                
            },
            formatter: (val: number) => {
                const value = Number(val);
                if (value === 0) return ''; 
                if (activeMetric.value === 'units') {
                    return value.toLocaleString('th-TH', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    });
                }
                return value.toLocaleString('th-TH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },
        },
        
        stroke: {
            width: [0, 4], 
            curve: 'smooth'
        },
        grid: {
            show: true,
            strokeDashArray: 4,
            borderColor: 'rgba(0, 0, 0, 0.1)'
        },
        xaxis: {
            categories: monthlyChartLabels.value,
          
        },
        
        // (!!! 7. ตั้งค่า 2 แกน Y (อัปเดต labels) !!!)
        yaxis: [
            {
                // (แกน Y ที่ 1 - สำหรับแท่ง)
                seriesName: 'Data',
               
                labels: {
                    show: false, // (!!! 1. เพิ่มบรรทัดนี้เพื่อปิดตัวเลขฝั่งซ้าย !!!)
                    formatter: (val: number) => {
                        if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                        if (val >= 1000) return (val / 1000).toFixed(0) + 'K';
                        return val.toFixed(0);
                    }
                }
            },
            {
                // (แกน Y ที่ 2 - สำหรับเส้น %)
                seriesName: '% เปลี่ยนแปลง (MoM)',
                opposite: true, 
            
                labels: {
                    show: false, // (!!! 2. เพิ่มบรรทัดนี้เพื่อปิดตัวเลขฝั่งขวา !!!)
                    formatter: (val: number) => (val ? val.toFixed(0) + '%' : '0%')
                }
            }
        ],
        tooltip: {
            theme: 'dark',
            y: {
                formatter: (val: number, { seriesIndex }: { seriesIndex: number }) => {
                    if (val === undefined || val === null) return 'N/A';
                    
                    if (seriesIndex === 0) { // (Tooltip สำหรับแท่ง)
                         return val.toLocaleString('th-TH', { maximumFractionDigits: 2 });
                    }
                    if (seriesIndex === 1) { // (Tooltip สำหรับเส้น)
                        return val.toFixed(1) + ' %';
                    }
                    return val.toString();
                }
            }
        },
        legend: {
            horizontalAlign: 'center',
            position: 'bottom',
            offsetY: 0
        }
    };
});


// (!!! ใหม่: computed สำหรับหา data source ปัจจุบัน !!!)
const currentMetricData = computed(() => {
    switch (activeMetric.value) {
        case 'units': return monthlyUnitsData.value;
        case 'value': return monthlyValueData.value;
        case 'area': return monthlyAreaData.value;
        case 'valuePerSqm': return monthlyValuePerSqmData.value;
        default: return [];
    }
});


// (!!! K. (ใหม่) สร้างข้อมูลสำหรับเส้น % เปลี่ยนแปลง !!!)
const monthlyPercentChangeData = computed(() => {
    // (!!! อัปเดต: ใช้ computed ใหม่ !!!)
    let sourceData: number[] = currentMetricData.value;
    
    // เลือกข้อมูลดิบตาม Metric ที่ใช้งาน
    // (ลบ logic เดิมออก)

    const changes: (number | null)[] = [null]; // เดือนแรกไม่มี % เทียบ
    
    for (let i = 1; i < sourceData.length; i++) {
        const prev = sourceData[i - 1];
        const curr = sourceData[i];
        
        if (prev > 0) {
            const change = ((curr - prev) / prev) * 100;
            changes.push(parseFloat(change.toFixed(1)));
        } else if (curr > 0) {
            changes.push(100); // ถ้าจาก 0 เป็นค่าบวก
        } else {
            changes.push(0); // ถ้าจาก 0 เป็น 0
        }
    }
    return changes;
});

// (!!! อัปเดต: mainGraphTitle (ใช้ logic จาก region.vue) !!!)
const mainGraphTitle = computed(() => {
    let baseTitle = '';
    switch (activeMetric.value) {
        case 'units': baseTitle = 'กราฟจำนวนหลัง'; break;
        case 'area': baseTitle = 'กราฟพื้นที่ใช้สอย'; break;
        case 'valuePerSqm': baseTitle = 'กราฟมูลค่าเฉลี่ย / ตร.ม.'; break;
        case 'value': default: baseTitle = 'กราฟสรุปมูลค่า'; break;
    }

    const yearText = ' ประจำปี ' + selectedYear.value;

    // 1. Check for specific Quarter
    if (selectedQuarter.value !== 'all') {
        const quarter = quarterOptions.value.find(q => q.value === selectedQuarter.value);
        return quarter ? `${baseTitle} ${quarter.title}${yearText}` : `${baseTitle}${yearText}`;
    }

    // 2. Check for "All Months" (YTD)
    const sortedMonthsKey = [...selectedMonths.value].sort((a, b) => a - b).join(',');
    const yearAD = selectedYear.value - 543;
    const allMonthsCurrentYear = allMonthItems.map((m) => m.value).slice(0, currentJsMonth).join(',');
    const allMonthsPastYear = allMonthItems.map((m) => m.value).join(',');

    if (sortedMonthsKey === allMonthsCurrentYear || sortedMonthsKey === allMonthsPastYear) {
        return `${baseTitle}${yearText}`;
    }

    // 3. Check for specific month range
    if (selectedMonths.value.length > 0) {
        const sortedMonthValues = [...selectedMonths.value].sort((a, b) => a - b);
        const firstMonthValue = sortedMonthValues[0];
        const lastMonthValue = sortedMonthValues[sortedMonthValues.length - 1];
        const firstMonth = allMonthItems.find((m) => m.value === firstMonthValue);
        const lastMonth = allMonthItems.find((m) => m.value === lastMonthValue);

        if (!firstMonth || !lastMonth) {
            return `${baseTitle}${yearText} (กำลังเลือกเดือน...)`;
        }
        if (firstMonthValue === lastMonthValue) {
            return `${baseTitle} ประจำเดือน ${firstMonth.title}${yearText}`;
        } else {
            return `${baseTitle} ประจำเดือน ${firstMonth.title} - ${lastMonth.title}${yearText}`;
        }
    }
    
    // 4. Fallback
    return `${baseTitle}${yearText}`;
});


// (!!! J. Chart Unit Subtitle (เหมือนเดิม) !!!)
const chartUnitSubtitle = computed(() => {
    switch (activeMetric.value) {
        case 'units':
            return '(หน่วย : หลัง)';
        case 'area':
            return '(หน่วย : ตร.ม.)';
        case 'valuePerSqm':
            return '(หน่วย : บาท / ตร.ม.)';
        case 'value':
        default:
            // (!!! แก้ไขเล็กน้อยให้ตรงกับ Chart)
            return '(หน่วย : บาท)';
    }
});

// (!!! K. Main Graph Series (เหมือนเดิม) !!!)
// (!!! K. Main Graph Series (ปรับปรุงใหม่) !!!)
// (!!! K. Main Graph Series (อัปเดต: ส่ง 2 ซีรีส์) !!!)
const mainGraphSeries = computed(() => {
    let barSeries: { name: string; type: 'bar'; data: number[] } | null = null;
    
    // (!!! อัปเดต: ใช้ computed ใหม่ !!!)
    const data = currentMetricData.value;

    switch (activeMetric.value) {
        case 'units':
            barSeries = { name: 'จำนวน (หลัง)', type: 'bar', data: data };
            break;
        case 'area':
            barSeries = { name: 'พื้นที่ (ตร.ม.)', type: 'bar', data: data };
            break;
        case 'valuePerSqm':
            barSeries = { name: 'มูลค่า/ตร.ม. (บาท)', type: 'bar', data: data };
            break;
        case 'value':
        default:
            barSeries = { name: 'มูลค่า (บาท)', type: 'bar', data: data };
            break;
    }

    // (!!! คืนค่า 2 ซีรีส์พร้อมกัน !!!)
    return [
        barSeries, // ซีรีส์ที่ 1 (แท่ง)
        {
            name: '% เปลี่ยนแปลง (MoM)', // ซีรีส์ที่ 2 (เส้น)
            type: 'line',
            data: monthlyPercentChangeData.value
        }
    ];
});

const regionalTableSubtitle = computed(() => {
    switch (activeMetric.value) {
        case 'units':
            return '(เปรียบเทียบตาม: จำนวนหลัง)';
        case 'area':
            return '(เปรียบเทียบตาม: พื้นที่ใช้สอย)';
        case 'valuePerSqm':
            return '(เปรียบเทียบตาม: มูลค่าเฉลี่ย / ตร.ม.)';
        case 'value':
        default:
            return '(เปรียบเทียบตาม: มูลค่า)';
    }
});


// (!!! อัปเดต: ลบ computed เก่า (chartAverage, chartTrendPercentage, averageVsLastMonthChange) !!!)
// const chartAverage = computed(() => { ... }); // ลบ
// const chartTrendPercentage = computed(() => { ... }); // ลบ
// const averageVsLastMonthChange = computed(() => { ... }); // ลบ


// (!!! ใหม่: Helpers สำหรับ format ตัวเลขสถิติ !!!)
const formatStatNumber = (val: number) => {
     if (activeMetric.value === 'units') {
        return val.toLocaleString('th-TH', { maximumFractionDigits: 0 });
     }
     // สำหรับ value, area, valuePerSqm
     return val.toLocaleString('th-TH', { maximumFractionDigits: 2 });
};

// (!!! อัปเดต: เปลี่ยนชื่อ formatStatPercentage เป็น formatPercentageHelper
// (เพื่อไม่ให้ซ้ำกับตัวแปร formatPercentage เดิมที่ใช้ในตาราง)
const formatPercentageHelper = (val: number) => {
    if (val === 0 || !val) return '0.0%';
    const prefix = val > 0 ? '+' : '';
    return `${prefix}${val.toFixed(1)}%`;
};

// (!!! ใหม่: computed สำหรับ "ส่วนต่าง" (Value) ของเดือนล่าสุด !!!)
const latestMonthDifference = computed(() => {
    const data = currentMetricData.value;
    
    // ต้องมีข้อมูลอย่างน้อย 2 เดือน ถึงจะเปรียบเทียบส่วนต่างได้
    if (data.length < 2) return 0;

    const latest = data[data.length - 1];
    const previous = data[data.length - 2];
    
    // คืนค่า (ยอดล่าสุด - ยอดเดือนก่อนหน้า)
    return latest - previous;
});

// (!!! ใหม่: computed สำหรับสถิติบนหัวกราฟ (แบบใหม่) !!!)
// (เรายังคงต้องใช้ 2 ตัวนี้ แม้ว่าจะเปลี่ยน logic filter แล้ว)

// 1. ดึง "ค่า" ของ "เดือนล่าสุด" ที่แสดงในกราฟ
const latestMonthValue = computed(() => {
    const data = currentMetricData.value;
    if (data.length === 0) return 0;
    // คืนค่าข้อมูลตัวสุดท้ายใน Array
    return data[data.length - 1];
});

// 2. ดึง "ค่า % MoM" ของ "เดือนล่าสุด" (จากเส้นสีแดง)
const latestMonthMoMChange = computed(() => {
    // monthlyPercentChangeData คือ Array ที่ใช้สร้างเส้นสีแดง [null, 10.5, -5.2]
    const momData = monthlyPercentChangeData.value;
    
    // ถ้ามีข้อมูลน้อยกว่า 2 เดือน (เช่น เลือก 1M) จะไม่มีค่า MoM
    if (momData.length < 2) return 0; 

    // คืนค่า % MoM ตัวสุดท้ายใน Array
    const latestChange = momData[momData.length - 1];
    
    // ถ้าค่าเป็น null (อาจเป็นเดือนแรก) ให้คืน 0
    return latestChange === null ? 0 : latestChange;
});
</script>

<template>

    <v-container fluid>

        <v-row v-if="userNotification.message">
            <v-col cols="12" sm="12" lg="12" class="pb-0">
                <v-alert density="compact" :type="userNotification.type" :text="userNotification.message"
                    :title="userNotification.title"></v-alert>
            </v-col>
        </v-row>


        <v-row>



            <v-col cols="12" sm="12" lg="12">
                <v-card elevation="10">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-select v-model="selectedYear" :items="yearOptions" label="ปี (พ.ศ.)"
                                    density="compact" variant="outlined" hide-details></v-select>
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-select v-model="selectedQuarter" :items="quarterOptions" item-title="title"
                                    item-value="value" label="ไตรมาส" density="compact" variant="outlined"
                                    hide-details></v-select>
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-select v-model="selectedMonths" :items="monthOptions" item-title="title"
                                    item-value="value" label="เดือน (เลือกได้หลายเดือน)" multiple chips closable-chips
                                    density="compact" variant="outlined" hide-details></v-select>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row class="mt-4">
            <v-col cols="12" sm="6" md="3">
                <v-card class="clickable-card" :variant="activeMetric === 'units' ? 'tonal' : 'elevated'" elevation="2"
                    @click="activeMetric = 'units'" :color="activeMetric === 'units' ? 'primary' : undefined">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center ga-4">
                            <v-btn icon color="primary" variant="elevated" elevation="0" density="default">
                                <v-icon icon="mdi-home-group" size="24"></v-icon>
                            </v-btn>
                            <div>
                                <h4 class="text-h4" :class="{ 'text-grey': loading }">
                                    {{ loading ? '...' : formattedSummary.units }}
                                </h4>
                                <p class="text-subtitle-1 text-grey-darken-1 mt-1">จำนวนหลัง (รวม)</p>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="3">
                <v-card class="clickable-card" :color="activeMetric === 'value' ? 'primary' : undefined"
                    :variant="activeMetric === 'value' ? 'tonal' : 'elevated'" elevation="2"
                    @click="activeMetric = 'value'">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center ga-4">
                            <v-btn icon color="secondary" variant="elevated" elevation="0" density="default">
                                <v-icon icon="mdi-cash-multiple" size="24"></v-icon>
                            </v-btn>
                            <div>
                                <h4 class="text-h4" :class="{ 'text-grey': loading }">
                                    {{ loading ? '...' : formattedSummary.value }}
                                </h4>
                                <p class="text-subtitle-1 text-grey-darken-1 mt-1">จำนวนมูลค่า (รวม)</p>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="3">
                <v-card class="clickable-card" :variant="activeMetric === 'area' ? 'tonal' : 'elevated'" elevation="2"
                    @click="activeMetric = 'area'" :color="activeMetric === 'area' ? 'primary' : undefined">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center ga-4">
                            <v-btn icon color="error" variant="elevated" elevation="0" density="default">
                                <v-icon icon="mdi-floor-plan" size="24"></v-icon>
                            </v-btn>
                            <div>
                                <h4 class="text-h4" :class="{ 'text-grey': loading }">
                                    {{ loading ? '...' : formattedSummary.area }}
                                </h4>
                                <p class="text-subtitle-1 text-grey-darken-1 mt-1">พื้นที่ใช้สอย (รวม)</p>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="3">
                <v-card class="clickable-card" :variant="activeMetric === 'valuePerSqm' ? 'tonal' : 'elevated'"
                    elevation="2" @click="activeMetric = 'valuePerSqm'"
                    :color="activeMetric === 'valuePerSqm' ? 'primary' : undefined">
                    <v-card-text class="pa-5">
                        <div class="d-flex align-center ga-4">
                            <v-btn icon color="warning" variant="elevated" elevation="0" density="default">
                                <v-icon icon="mdi-chart-bar" size="24"></v-icon>
                            </v-btn>
                            <div>
                                <h4 class="text-h4" :class="{ 'text-grey': loading }">
                                    {{ loading ? '...' : formattedSummary.valuePerSqm }}
                                </h4>
                                <p class="text-subtitle-1 text-grey-darken-1 mt-1">มูลค่าเฉลี่ย / ตร.ม.</p>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row class="mt-4">
            <v-col cols="12">
                <v-card elevation="2">
                    
                    <v-card-title class="pa-4">
                        <v-row align="start">
                            
                            <v-col cols="12">
                                <h3 class="card-title mb-1">
                                    {{ mainGraphTitle }} </h3>
                                
                                <v-row v-if="!loading && currentMetricData.length > 0" align="center" justify="start" class="mt-2">
                                    
                                    <v-col cols="auto"> 
                                        <h3 class="card-title" style="font-size: 1.25rem;"> 
                                            <span :class="getPercentageColor(latestMonthDifference)">
                                                
                                                {{ formatStatNumber(latestMonthDifference) }} 

                                                <v-icon v-if="latestMonthDifference > 0" size="small" class="ml-1">mdi-arrow-up</v-icon>
                                                <v-icon v-else-if="latestMonthDifference < 0" size="small" class="ml-1">mdi-arrow-down</v-icon>
                                            </span>
                                        </h3>
                                        <h5 class="card-subtitle text-grey-darken-1">เปลี่ยนแปลง (MoM)</h5>
                                    </v-col>

                                    <v-col cols="auto"> 
                                        <h3 classs="card-title" :class="getPercentageColor(latestMonthMoMChange)" style="font-size: 1.25rem;">
                                            {{ formatPercentageHelper(latestMonthMoMChange) }}
                                        </h3>
                                        <h5 class="card-subtitle text-grey-darken-1"> % MoM </h5>
                                    </v-col>

                                </v-row>
                                
                                <div v-else-if="!loading" class="text-grey">
                                    -
                                </div>
                           
                            </v-col>
                            
                            </v-row>
                    </v-card-title>
                    <v-divider></v-divider>

                    <v-card-text style="min-height: 365px">
                        <v-skeleton-loader v-if="loading" type="image" height="350"></v-skeleton-loader>

                        <VueApexCharts v-else-if="!loading && monthlyChartLabels.length > 0" :options="chartOptions"
                            :series="mainGraphSeries" height="350" :key="activeMetric" />
                        <div v-else-if="!loading && monthlyChartLabels.length === 0"
                            class="d-flex align-center justify-center text-grey-darken-1" style="height: 350px">
                            ไม่พบข้อมูลสำหรับตัวกรองที่คุณเลือก
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row class="mt-4">
            <v-col cols="12">
                <v-card elevation="2">
                    <v-card-title class="pa-4">
                        <h3 class="card-title mb-1">
                            ข้อมูลเปรียบเทียบรายภูมิภาค
                        </h3>
                        <h5 class="card-subtitle" style="text-align: left;">
                            {{ regionalTableSubtitle }}
                        </h5>
                    </v-card-title>
                    <v-divider></v-divider>

                    <v-card-text>
                        <v-data-table-virtual :headers="regionalTableHeaders" :items="regionalTableData"
                            :loading="loadingRegional" :items-per-page="10" class="elevation-0" density="compact">

                            <template v-slot:item.current_period="{ item }">
                                <span class="text-end d-block">{{ item.current_period.toLocaleString('th-TH', {
                                    maximumFractionDigits: (activeMetric === 'units' || activeMetric === 'area') ? 0 : 2
                                }) }}</span>
                            </template>
                            <template v-slot:item.cytd="{ item }">
                                <span class="text-end d-block">{{ item.cytd.toLocaleString('th-TH', {
                                    maximumFractionDigits: (activeMetric === 'units' || activeMetric === 'area') ? 0 : 2
                                }) }}</span>
                            </template>
                            <template v-slot:item.pytd="{ item }">
                                <span class="text-end d-block">{{ item.pytd.toLocaleString('th-TH', {
                                    maximumFractionDigits: (activeMetric === 'units' || activeMetric === 'area') ? 0 : 2
                                }) }}</span>
                            </template>

                            <template v-slot:item.yoy_change="{ item }">
                                <span :class="['font-weight-bold', getPercentageColor(item.yoy_change)]">
                                    {{ formatPercentage(item.yoy_change) }}
                                </span>
                            </template>

                            <template v-slot:item.mom_change="{ item }">
                                <span :class="['font-weight-bold', getPercentageColor(item.mom_change)]">
                                    {{ formatPercentage(item.mom_change) }}
                                </span>
                            </template>

                            <template v-slot:item.ytd_change="{ item }">
                                <span :class="['font-weight-bold', getPercentageColor(item.ytd_change)]">
                                    {{ formatPercentage(item.ytd_change) }}
                                </span>
                            </template>

                            <template v-slot:no-data>
                                <div class="pa-4 text-center text-grey">
                                    ไม่พบข้อมูลรายภูมิภาคสำหรับตัวกรองที่คุณเลือก
                                </div>
                            </template>

                        </v-data-table-virtual>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

    </v-container>
</template>

<style scoped>
.clickable-card {
    cursor: pointer;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

/* นี่คือ effect ตอน hover ครับ */
.clickable-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>