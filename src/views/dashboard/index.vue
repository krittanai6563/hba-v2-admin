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

type VuetifyAlertType = "success" | "warning" | "error" | "info" | undefined;

interface UserNotification {
    message: string;
    type: VuetifyAlertType;
    title: string;
}


const userNotification = computed((): UserNotification => { // <--- [แก้ไขจุดที่ 1]
    
    // แสดงเฉพาะสำหรับบทบาท 'user'
    if (userRole.value !== 'user') {
        return { message: '', type: undefined, title: '' }; // <--- [แก้ไขจุดที่ 2]
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
        // [แก้ไขจุดที่ 3] กำหนด Type ของตัวแปร type ให้แคบลง
        let type: 'warning' | 'error' = 'warning'; 
        
        if (days > 0) {
            message = `เหลืออีก ${days} วัน ในการกรอกข้อมูล ก่อนวันที่ ${deadlineDay} ${currentMonthName} ${currentYear}`;
            if (days <= 2) {
                type = 'error';
            }
        } else { // วันที่ 10 พอดี
            message = `ถึงกำหนดส่งข้อมูลวันนี้! กรุณากรอกข้อมูลก่อนสิ้นสุดวันที่ ${deadlineDay} ${currentMonthName} ${currentYear}`;
            type = 'error';
        }
        
        // [แก้ไขจุดที่ 4] ไม่จำเป็นต้องใช้ 'as' แล้ว เพราะ Type ถูกต้องแล้ว
        return { message: message, type: type, title: 'กรุณากรอกข้อมูล' };
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
    
    return { message: '', type: undefined, title: '' }; // <--- [แก้ไขจุดที่ 5]
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

// (!!! อัปเดต: เพิ่ม short (ชื่อย่อ) !!!)
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

// (!!! ใหม่: เพิ่ม Ref สำหรับปุ่มเลือกช่วงเวลา (และ 'custom') !!!)
type Period = '1M' | '3M' | '6M' | 'YTD' | 'custom';
const selectedPeriod = ref<Period>('YTD');


const isUpdatingFromMonths = ref(false);

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

// (!!! อัปเดต: แยก Ref สำหรับ "กราฟ" (Selected) และ "ตาราง" (Full) !!!)

// (1) Refs สำหรับ "กราฟ" (แสดงเฉพาะเดือนที่เลือก)
const selectedMonthlyChartLabels = ref<string[]>([]);
const selectedMonthlyUnitsData = ref<number[]>([]);
const selectedMonthlyValueData = ref<number[]>([]);
const selectedMonthlyAreaData = ref<number[]>([]);
const selectedMonthlyValuePerSqmData = ref<number[]>([]);

// (2) Refs สำหรับ "ตาราง" (เก็บข้อมูลเต็มตั้งแต่ ม.ค. ... เดือนล่าสุดที่เลือก)
const fullMonthlyLabels = ref<string[]>([]);
// (CY = Current Year)
const fullMonthlyUnitsData_CY = ref<number[]>([]);
const fullMonthlyValueData_CY = ref<number[]>([]);
const fullMonthlyAreaData_CY = ref<number[]>([]);
const fullMonthlyValuePerSqmData_CY = ref<number[]>([]);
// (PY = Previous Year)
const fullMonthlyUnitsData_PY = ref<number[]>([]);
const fullMonthlyValueData_PY = ref<number[]>([]);
const fullMonthlyAreaData_PY = ref<number[]>([]);
const fullMonthlyValuePerSqmData_PY = ref<number[]>([]);


const loadingRegional = ref(false);
type Metric = 'units' | 'value' | 'area' | 'valuePerSqm';
const activeMetric = ref<Metric>('value');
// (--- จบส่วนโค้ดเดิม ---)


// --- (!!! เพิ่ม: ฟังก์ชันดึงสถานะผู้ใช้ (ข้อมูลและสัญญา) !!!) ---
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


// --- (!!! อัปเดต: 3. ฟังก์ชันหลักในการดึงข้อมูล (FetchData) (อัปเดตครั้งใหญ่) !!!) ---
const fetchData = async () => {
    // (A. ส่วน Logic ตรวจสอบค่าว่าง)
    if (selectedMonths.value.length === 0 || !selectedYear.value) {
        summaryData.value = { total_units: 0, total_value: 0, total_area: 0, value_per_sqm: 0 };
        // (รีเซ็ต "กราฟ")
        selectedMonthlyChartLabels.value = []; selectedMonthlyUnitsData.value = [];
        selectedMonthlyValueData.value = []; selectedMonthlyAreaData.value = [];
        selectedMonthlyValuePerSqmData.value = [];
        // (รีเซ็ต "ตาราง")
        fullMonthlyLabels.value = [];
        fullMonthlyUnitsData_CY.value = []; fullMonthlyValueData_CY.value = [];
        fullMonthlyAreaData_CY.value = []; fullMonthlyValuePerSqmData_CY.value = [];
        fullMonthlyUnitsData_PY.value = []; fullMonthlyValueData_PY.value = [];
        fullMonthlyAreaData_PY.value = []; fullMonthlyValuePerSqmData_PY.value = [];
        
        return;
    }

    // (B. สั่ง loading ทั้งคู่)
    loading.value = true;
    loadingRegional.value = true; // (ยังใช้ loadingRegional สำหรับตาราง)

    try {
        // (!!! 1. (ใหม่) หาเดือนทั้งหมดที่ต้องใช้คำนวณ (ตั้งแต่ 1 ถึงเดือนล่าสุดที่เลือก) !!!)
        const maxMonth = Math.max(...selectedMonths.value);
        if (maxMonth <= 0) return; // (Safety check)
        
        // (!!! (อัปเดต) ถ้า maxMonth < 3 (Q1) เราต้องดึง Q1 ให้ครบเพื่อคำนวณ QoQ !!!)
        // (!!! (อัปเดต 2) ไม่ เราดึงแค่ 1...maxMonth ก็พอ QoQ จะเริ่มคำนวณที่ Q2)
        const monthsToFetch = Array.from({ length: maxMonth }, (_, i) => i + 1);


        // (!!! 2. เตรียม Payload สำหรับปีปัจจุบัน (CY) (ใช้ monthsToFetch) !!!)
        const yearAD = selectedYear.value - 543;
        const payload_CY: any = {
            year: yearAD,
            months: monthsToFetch, // (!!! อัปเดต !!!)
            role: userRole.value
        };
        if (!isAdmin.value && userId) {
            payload_CY.user_id = userId;
        }
        const bodyPayload_CY = JSON.stringify(payload_CY);
        
        // (!!! 3. เตรียม Payload สำหรับปีก่อนหน้า (PY) (ใช้ monthsToFetch) !!!)
        const yearPY_AD = yearAD - 1;
        const payload_PY: any = {
            year: yearPY_AD,
            months: monthsToFetch, // (!!! อัปเดต !!!)
            role: userRole.value
        };
         if (!isAdmin.value && userId) {
            payload_PY.user_id = userId;
        }
        const bodyPayload_PY = JSON.stringify(payload_PY);


        // (!!! 4. สร้าง Promise (เหลือ 2 ตัว ไม่เอา regional แล้ว) !!!)
        const chartApiUrl = 'https://uat.hba-sales.org/backend/get_dashboard_data.php';
        
        const fetchOptions_CY = { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: bodyPayload_CY };
        const fetchOptions_PY = { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: bodyPayload_PY };

        const chartPromise_CY = fetch(chartApiUrl, fetchOptions_CY).then(res => res.json());
        const chartPromise_PY = fetch(chartApiUrl, fetchOptions_PY).then(res => res.json());

        // (!!! 5. รอให้ทั้ง 2 ตัวเสร็จสิ้น !!!)
        const [chartResponse_CY, chartResponse_PY] = await Promise.all([
            chartPromise_CY, 
            chartPromise_PY
        ]);

        // (!!! 6. ประมวลผล Chart (CY) - (อัปเดต) !!!)
        if (chartResponse_CY.status === 'success') {
            summaryData.value = chartResponse_CY.data.summary;
            const monthly = chartResponse_CY.data.monthly_data;
            
            // (6.1 เก็บข้อมูล "เต็ม" สำหรับตาราง)
            fullMonthlyLabels.value = monthly.labels; // (ได้ ["ม.ค.", "ก.พ.", ... "มิ.ย."])
            fullMonthlyUnitsData_CY.value = monthly.units;
            fullMonthlyValueData_CY.value = monthly.value;
            fullMonthlyAreaData_CY.value = monthly.area;
            fullMonthlyValuePerSqmData_CY.value = monthly.valuePerSqm;
            
            // (6.2 กรองข้อมูล "เฉพาะที่เลือก" สำหรับกราฟ)
            // (6.2 กรองข้อมูล "เฉพาะที่เลือก" สำหรับกราฟ)
            const selectedIndexes = selectedMonths.value.map(m => m - 1); // (เช่น Q2 -> [3, 4, 5])

            // (!!! อัปเดต: เพิ่ม (value: unknown, index: number) เพื่อแก้ Error TS(7006) !!!)
            selectedMonthlyChartLabels.value = monthly.labels.filter((value: unknown, index: number) => selectedIndexes.includes(index));
            selectedMonthlyUnitsData.value = monthly.units.filter((value: unknown, index: number) => selectedIndexes.includes(index));
            selectedMonthlyValueData.value = monthly.value.filter((value: unknown, index: number) => selectedIndexes.includes(index));
            selectedMonthlyAreaData.value = monthly.area.filter((value: unknown, index: number) => selectedIndexes.includes(index));
            selectedMonthlyValuePerSqmData.value = monthly.valuePerSqm.filter((value: unknown, index: number) => selectedIndexes.includes(index));

        } else {
            console.error('Error fetching chart data (CY):', chartResponse_CY.message);
        }
        
        // (!!! 7. (ใหม่) ประมวลผล Chart (PY) (เก็บข้อมูล "เต็ม" สำหรับตาราง) !!!)
         if (chartResponse_PY.status === 'success') {
            const monthly_PY = chartResponse_PY.data.monthly_data;
            fullMonthlyUnitsData_PY.value = monthly_PY.units;
            fullMonthlyValueData_PY.value = monthly_PY.value;
            fullMonthlyAreaData_PY.value = monthly_PY.area;
            fullMonthlyValuePerSqmData_PY.value = monthly_PY.valuePerSqm;
        } else {
            console.error('Error fetching PY chart data:', chartResponse_PY.message);
            const monthCount = fullMonthlyLabels.value.length; // (ใช้จำนวนเดือนของ CY เป็นหลัก)
            fullMonthlyUnitsData_PY.value = Array(monthCount).fill(0);
            fullMonthlyValueData_PY.value = Array(monthCount).fill(0);
            fullMonthlyAreaData_PY.value = Array(monthCount).fill(0);
            fullMonthlyValuePerSqmData_PY.value = Array(monthCount).fill(0);
        }


    } catch (error) {
        console.error('Fetch error:', error);
    } finally {
        // (G. ปิด loading ทั้งคู่)
        loading.value = false;
        loadingRegional.value = false;
    }
};
// (!!! จบการอัปเดต fetchData !!!)


// (!!! ใหม่: เพิ่ม setPeriod (จาก Step 20) !!!)
const setPeriod = (period: Period) => {
    selectedPeriod.value = period;

    // 1. Get all available months (Jan...CurrentMonth)
    const yearAD = selectedYear.value - 543;
    let availableMonths: number[] = [];
    if (yearAD === currentJsYear) {
        availableMonths = allMonthItems.map(m => m.value).filter(m => m <= currentJsMonth);
    } else if (yearAD > currentJsYear) {
        availableMonths = [];
    } else {
        availableMonths = allMonthItems.map(m => m.value);
    }

    // 2. Set selectedMonths based on the "rolling" window
    if (period === '1M') {
        selectedMonths.value = availableMonths.slice(-1);
    } else if (period === '3M') {
        selectedMonths.value = availableMonths.slice(-3);
    } else if (period === '6M') {
        selectedMonths.value = availableMonths.slice(-6);
    } else { // 'YTD'
        selectedMonths.value = availableMonths;
        // (!!! อัปเดต: ถ้ากด YTD ให้ซิงค์ Dropdown Q ด้วย !!!)
        if (selectedQuarter.value !== 'all') {
            isUpdatingFromMonths.value = true; // (ป้องกัน watch(selectedQuarter) ทำงาน)
            selectedQuarter.value = 'all';
        }
    }
};


// --- (!!! อัปเดต: Logic Filters & onMounted (รวม 2 ระบบ) !!!) ---
watch(selectedQuarter, (newQuarter) => {
    if (isUpdatingFromMonths.value) {
        isUpdatingFromMonths.value = false;
        return;
    }
    const validMonthValues = monthOptions.value.map(m => m.value);
    const filterValidMonths = (months: number[]) => months.filter(m => validMonthValues.includes(m));

    if (newQuarter === 'all') {
         // (ถ้าผู้ใช้ "เลือก" All Q (ไม่ใช่เพราะ sync) ให้ตั้งเป็น YTD)
         // (ปกติ watch(selectedMonths) จะคุม แต่อันนี้กันไว้)
         updateToAllMonths(); 
    }
    else if (newQuarter === 'Q1') selectedMonths.value = filterValidMonths([1, 2, 3]);
    else if (newQuarter === 'Q2') selectedMonths.value = filterValidMonths([4, 5, 6]);
    else if (newQuarter === 'Q3') selectedMonths.value = filterValidMonths([7, 8, 9]);
    else if (newQuarter === 'Q4') selectedMonths.value = filterValidMonths([10, 11, 12]);
});

watch(selectedYear, () => {
    // (เมื่อปีเปลี่ยน, ให้ใช้ "ปุ่ม" หรือ "Q" ที่เคยเลือกไว้)
    if(selectedQuarter.value !== 'all') {
         // (ถ้าเลือก Q ไว้ ให้รัน logic ของ Q ใหม่)
         const validMonths = monthOptions.value.map((m) => m.value);
         selectedMonths.value = selectedMonths.value.filter((m) => validMonths.includes(m));
         
         const currentQuarter = selectedQuarter.value;
         selectedQuarter.value = ''; 
         selectedQuarter.value = currentQuarter;
    } else {
         // (ถ้าไม่ได้เลือก Q (คือใช้ปุ่ม) ให้รัน logic ของปุ่มใหม่)
         setPeriod(selectedPeriod.value);
    }
});

watch(
    selectedMonths,
    (newMonths, oldMonths) => {
        
        const sortedMonths = [...newMonths].sort((a, b) => a - b).join(',');
        
        const validMonthValues = monthOptions.value.map(m => m.value);
        
        const q1Months = [1, 2, 3].filter(m => validMonthValues.includes(m)).join(',');
        const q2Months = [4, 5, 6].filter(m => validMonthValues.includes(m)).join(',');
        const q3Months = [7, 8, 9].filter(m => validMonthValues.includes(m)).join(',');
        const q4Months = [10, 11, 12].filter(m => validMonthValues.includes(m)).join(',');

        // (!!! 1. ซิงค์ Dropdown 'ไตรมาส' !!!)
        if (sortedMonths === q1Months && q1Months.length > 0) selectedQuarter.value = 'Q1';
        else if (sortedMonths === q2Months && q2Months.length > 0) selectedQuarter.value = 'Q2';
        else if (sortedMonths === q3Months && q3Months.length > 0) selectedQuarter.value = 'Q3';
        else if (sortedMonths === q4Months && q4Months.length > 0) selectedQuarter.value = 'Q4';
        else {
            const yearAD = selectedYear.value - 543;
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

        // (!!! 2. ซิงค์ "ปุ่ม" (1M, 3M, ...) !!!)
        const yearAD = selectedYear.value - 543;
        let availableMonths: number[] = [];
        if (yearAD === currentJsYear) availableMonths = allMonthItems.map(m => m.value).filter(m => m <= currentJsMonth);
        else if (yearAD > currentJsYear) availableMonths = [];
        else availableMonths = allMonthItems.map(m => m.value);
        
        const availableMonthsKey = availableMonths.join(',');
        const last6MonthsKey = availableMonths.slice(-6).join(',');
        const last3MonthsKey = availableMonths.slice(-3).join(',');
        const last1MonthKey = availableMonths.slice(-1).join(',');

        if (sortedMonths === availableMonthsKey) selectedPeriod.value = 'YTD';
        else if (sortedMonths === last6MonthsKey) selectedPeriod.value = '6M';
        else if (sortedMonths === last3MonthsKey) selectedPeriod.value = '3M';
        else if (sortedMonths === last1MonthKey) selectedPeriod.value = '1M';
        else selectedPeriod.value = 'custom'; // (เช่น ถ้าเลือก Q1, Q2, หรือ [1, 5, 9])


        // (!!! 3. ดึงข้อมูล (สำคัญ!) !!!)
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
    // (!!! อัปเดต: กลับไปใช้ setPeriod('YTD') เพื่อเริ่ม !!!)
    setPeriod('YTD');
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

// (!!! F. Helpers สำหรับตารางใหม่ (เหมือนเดิม) !!!)
const formatPercentage = (value: number) => {
    if (value === 0 || !isFinite(value)) return '0.0%';
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

// (!!! H. (อัปเดต!) Chart Options (ปรับปรุงความสวยงาม) !!!)
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
            },
            dropShadow: {
                enabled: true,
                enabledOnSeries: [1],
                top: 3,
                left: 0,
                blur: 3,
                color: '#E53935',
                opacity: 0.35
            },
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
                    return value.toLocaleString('th-TH');
                }
                if (value >= 1000000) {
                    return (value / 1000000).toFixed(1) + 'M';
                }
                if (value >= 1000) {
                    return (value / 1000).toFixed(0) + 'K';
                }
                return value.toLocaleString('th-TH', { maximumFractionDigits: 0 });
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
            categories: selectedMonthlyChartLabels.value, // (!!! อัปเดต: ใช้ selected... !!!)
        },
        
        yaxis: [
            {
                seriesName: 'Data',
                labels: {
                    show: true, 
                    formatter: (val: number) => {
                        if (val >= 1000000) return (val / 1000000).toFixed(0) + 'M';
                        if (val >= 1000) return (val / 1000).toFixed(0) + 'K';
                        return val.toFixed(0);
                    }
                }
            },
            {
                seriesName: '% เปลี่ยนแปลง (MoM)',
                opposite: true, 
                labels: {
                    show: true, 
                    formatter: (val: number) => (val ? val.toFixed(0) + '%' : '0%')
                }
            }
        ],
        tooltip: {
            theme: 'dark',
            shared: true, 
            intersect: false, 
            y: {
                formatter: (val: number, { seriesIndex }: { seriesIndex: number }) => {
                    if (val === undefined || val === null) return 'N/A';
                    
                    if (seriesIndex === 0) {
                         return val.toLocaleString('th-TH', { maximumFractionDigits: 2 });
                    }
                    if (seriesIndex === 1) {
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
        },
        markers: {
            size: 0,
            hover: {
                size: 5
            }
        }
    };
});
// (!!! จบการอัปเดต chartOptions !!!)


// (!!! อัปเดต: computed สำหรับหา data source (กราฟ) !!!)
const currentMetricData = computed(() => {
    switch (activeMetric.value) {
        case 'units': return selectedMonthlyUnitsData.value;
        case 'value': return selectedMonthlyValueData.value;
        case 'area': return selectedMonthlyAreaData.value;
        case 'valuePerSqm': return selectedMonthlyValuePerSqmData.value;
        default: return [];
    }
});

// (!!! อัปเดต: computed สำหรับหา data source "เต็ม" (ตาราง) !!!)
const currentMetricData_CY_FULL = computed(() => {
    switch (activeMetric.value) {
        case 'units': return fullMonthlyUnitsData_CY.value;
        case 'value': return fullMonthlyValueData_CY.value;
        case 'area': return fullMonthlyAreaData_CY.value;
        case 'valuePerSqm': return fullMonthlyValuePerSqmData_CY.value;
        default: return [];
    }
});
const currentMetricData_PY_FULL = computed(() => {
    switch (activeMetric.value) {
        case 'units': return fullMonthlyUnitsData_PY.value;
        case 'value': return fullMonthlyValueData_PY.value;
        case 'area': return fullMonthlyAreaData_PY.value;
        case 'valuePerSqm': return fullMonthlyValuePerSqmData_PY.value;
        default: return [];
    }
});


// (!!! K. (อัปเดต) สร้างข้อมูลสำหรับเส้น % เปลี่ยนแปลง (กราฟ) !!!)
const monthlyPercentChangeData = computed(() => {
    let sourceData: number[] = currentMetricData.value; // (ใช้ข้อมูล "กราฟ" ที่กรองแล้ว)

    const changes: (number | null)[] = [null]; 
    
    for (let i = 1; i < sourceData.length; i++) {
        const prev = sourceData[i - 1];
        const curr = sourceData[i];
        
        if (prev > 0) {
            const change = ((curr - prev) / prev) * 100;
            changes.push(parseFloat(change.toFixed(1)));
        } else if (curr > 0) {
            changes.push(100);
        } else {
            changes.push(0);
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

    // (!!! อัปเดต: ให้ชื่อ Title อิงตาม "ปุ่ม" ที่เลือก (ถ้ามี) !!!)
    if (selectedPeriod.value === '1M') return `${baseTitle} (1 เดือนล่าสุด)${yearText}`;
    if (selectedPeriod.value === '3M') return `${baseTitle} (3 เดือนล่าสุด)${yearText}`;
    if (selectedPeriod.value === '6M') return `${baseTitle} (6 เดือนล่าสุด)${yearText}`;

    // (ถ้าปุ่มเป็น YTD หรือ custom (เลือกจาก Q))
    if (selectedQuarter.value !== 'all') {
        const quarter = quarterOptions.value.find(q => q.value === selectedQuarter.value);
        return quarter ? `${baseTitle} ${quarter.title}${yearText}` : `${baseTitle}${yearText}`;
    }

    // (ถ้าปุ่มเป็น YTD)
    if (selectedPeriod.value === 'YTD') {
         return `${baseTitle}${yearText}`;
    }
    
    // (Fallback กรณีเลือกเดือนเอง [1, 5, 9])
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
            return '(หน่วย : บาท)';
    }
});

// (!!! K. Main Graph Series (อัปเดต) !!!)
const mainGraphSeries = computed(() => {
    let barSeries: { name: string; type: 'bar'; data: number[] } | null = null;
    
    const data = currentMetricData.value; // (!!! ใช้ data "กราฟ" ที่กรองแล้ว !!!)

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

    return [
        barSeries, // ซีรีส์ที่ 1 (แท่ง)
        {
            name: '% เปลี่ยนแปลง (MoM)', // ซีรีส์ที่ 2 (เส้น)
            type: 'line',
            data: monthlyPercentChangeData.value // (!!! ใช้ data "กราฟ" ที่กรองแล้ว !!!)
        }
    ];
});


// (!!! ใหม่: Helpers สำหรับ format ตัวเลขสถิติ !!!)
const formatStatNumber = (val: number) => {
     if (activeMetric.value === 'units') {
        return val.toLocaleString('th-TH', { maximumFractionDigits: 0 });
     }
     return val.toLocaleString('th-TH', { maximumFractionDigits: 2 });
};

const formatPercentageHelper = (val: number) => {
    if (val === 0 || !isFinite(val)) return '0.0%';
    const prefix = val > 0 ? '+' : '';
    return `${prefix}${val.toFixed(1)}%`;
};

// (!!! ใหม่: computed สำหรับ "ส่วนต่าง" (Value) ของเดือนล่าสุด !!!)
const latestMonthDifference = computed(() => {
    const data = currentMetricData.value; // (!!! ใช้ data "กราฟ" !!!)
    
    if (data.length < 2) return 0;
    const latest = data[data.length - 1];
    const previous = data[data.length - 2];
    
    return latest - previous;
});


// 1. ดึง "ค่า" ของ "เดือนล่าสุด" ที่แสดงในกราฟ
const latestMonthValue = computed(() => {
    const data = currentMetricData.value; // (!!! ใช้ data "กราฟ" !!!)
    if (data.length === 0) return 0;
    return data[data.length - 1];
});

// 2. ดึง "ค่า % MoM" ของ "เดือนล่าสุด" (จากเส้นสีแดง)
const latestMonthMoMChange = computed(() => {
    const momData = monthlyPercentChangeData.value; // (!!! ใช้ data "กราฟ" !!!)
    if (momData.length < 2) return 0; 
    const latestChange = momData[momData.length - 1];
    return latestChange === null ? 0 : latestChange;
});


// (!!! อัปเดต: computed สำหรับตารางรายเดือน (ตัวหลัก) (เพิ่ม QoQ) !!!)
const monthlyComparisonTableData = computed(() => {
    const labels = fullMonthlyLabels.value; // (["ม.ค.", "ก.พ.", ...])
    const cy_data = currentMetricData_CY_FULL.value; // (ข้อมูลเต็ม 1...maxMonth)
    const py_data = currentMetricData_PY_FULL.value; // (ข้อมูลเต็ม 1...maxMonth)

    let cytd = 0;
    let pytd = 0;
    
    // (!!! ใหม่: เก็บผลรวมไตรมาส !!!)
    const quarterlySums_CY: number[] = [0, 0, 0, 0]; // (Q1, Q2, Q3, Q4)

    const tableData = labels.map((shortLabel, index) => {
        // (!!! อัปเดต: หาชื่อเดือนเต็ม !!!)
        const monthIndex = index + 1; // (1, 2, 3...)
        const monthFullName = allMonthItems.find(m => m.short === shortLabel)?.title || shortLabel;

        const cy_value = cy_data[index] || 0;
        const py_value = py_data[index] || 0;
        
        // (MoM)
        const prev_cy_value = index > 0 ? (cy_data[index - 1] || 0) : 0;
        let mom_percent = 0;
        if (prev_cy_value > 0) {
            mom_percent = ((cy_value - prev_cy_value) / prev_cy_value) * 100;
        } else if (cy_value > 0) {
            mom_percent = 100;
        }

        // (YOY)
        let yoy_percent = 0;
        if (py_value > 0) {
            yoy_percent = ((cy_value - py_value) / py_value) * 100;
        } else if (cy_value > 0) {
            yoy_percent = 100;
        }

        // (YTD)
        cytd += cy_value;
        pytd += py_value;
        let ytd_percent = 0;
        if (pytd > 0) {
            ytd_percent = ((cytd - pytd) / pytd) * 100;
        } else if (cytd > 0) {
            ytd_percent = 100;
        }

        // (!!! ใหม่: สะสมค่า QoQ !!!)
        if (monthIndex <= 3) quarterlySums_CY[0] += cy_value;
        else if (monthIndex <= 6) quarterlySums_CY[1] += cy_value;
        else if (monthIndex <= 9) quarterlySums_CY[2] += cy_value;
        else if (monthIndex <= 12) quarterlySums_CY[3] += cy_value;
        
        // (!!! ใหม่: คำนวณ QoQ% (เฉพาะเดือนสิ้นไตรมาส) !!!)
        let qoq_percent: number | null = null;
        if (monthIndex === 3) {
             qoq_percent = null; // (Q1 ไม่มีตัวเทียบ Q ก่อนหน้า)
        } else if (monthIndex === 6) { // Q2 vs Q1
            const q1_sum = quarterlySums_CY[0];
            const q2_sum = quarterlySums_CY[1];
            if(q1_sum > 0) qoq_percent = ((q2_sum - q1_sum) / q1_sum) * 100;
            else if (q2_sum > 0) qoq_percent = 100;
            else qoq_percent = 0;
        } else if (monthIndex === 9) { // Q3 vs Q2
            const q2_sum = quarterlySums_CY[1];
            const q3_sum = quarterlySums_CY[2];
             if(q2_sum > 0) qoq_percent = ((q3_sum - q2_sum) / q2_sum) * 100;
            else if (q3_sum > 0) qoq_percent = 100;
            else qoq_percent = 0;
        } else if (monthIndex === 12) { // Q4 vs Q3
            const q3_sum = quarterlySums_CY[2];
            const q4_sum = quarterlySums_CY[3];
             if(q3_sum > 0) qoq_percent = ((q4_sum - q3_sum) / q3_sum) * 100;
            else if (q4_sum > 0) qoq_percent = 100;
            else qoq_percent = 0;
        }

        return {
            monthValue: monthIndex, // (!!! ใหม่: เก็บ value (1,2,3) ไว้กรอง !!!)
            month: monthFullName,
            cy_value: cy_value,
            py_value: py_value,
            yoy_percent: yoy_percent,
            mom_percent: mom_percent,
            qoq_percent: qoq_percent, // (!!! ใหม่ !!!)
            cytd: cytd,
            pytd: pytd,
            ytd_percent: ytd_percent
        };
    });

    // (!!! สุดท้าย: กรองตาราง ให้แสดงเฉพาะเดือนที่ผู้ใช้เลือก !!!)
    return tableData.filter(item => selectedMonths.value.includes(item.monthValue));
});
// (!!! จบการอัปเดต monthlyComparisonTableData !!!)


// (!!! อัปเดต: computed สำหรับ Headers ตารางรายเดือน (เพิ่ม QoQ) !!!)
const monthlyComparisonTableHeaders = computed(() => {
    const currentYearBE = selectedYear.value;     // เช่น 2568
    const previousYearBE = selectedYear.value - 1; // เช่น 2567

    let metricName = 'ยอด'; // Default
    if (activeMetric.value === 'units') metricName = 'จำนวน (หลัง)';
    else if (activeMetric.value === 'value') metricName = 'มูลค่า (บาท)';
    else if (activeMetric.value === 'area') metricName = 'พื้นที่ (ตร.ม.)';
    else if (activeMetric.value === 'valuePerSqm') metricName = 'มูลค่า/ตร.ม.';

    return [
        { title: 'เดือน', key: 'month', align: 'start', sortable: false, width: '15%' },
        { title: `${metricName} ${currentYearBE}`, key: 'cy_value', align: 'end', sortable: false },
        { title: `${metricName} ${previousYearBE}`, key: 'py_value', align: 'end', sortable: false },
        { title: 'YOY %', key: 'yoy_percent', align: 'end', sortable: false },
        { title: 'MoM %', key: 'mom_percent', align: 'end', sortable: false },
        { title: 'QoQ %', key: 'qoq_percent', align: 'end', sortable: false }, // (!!! ใหม่ !!!)
        { title: `YTD ${currentYearBE}`, key: 'cytd', align: 'end', sortable: false },
        { title: `YTD ${previousYearBE}`, key: 'pytd', align: 'end', sortable: false },
        { title: 'YTD %', key: 'ytd_percent', align: 'end', sortable: false },
    ] as const;
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <g fill="none" stroke="currentColor" stroke-width="1.5">
                                <path
                                    d="M2 12.204c0-2.289 0-3.433.52-4.381c.518-.949 1.467-1.537 3.364-2.715l2-1.241C9.889 2.622 10.892 2 12 2s2.11.622 4.116 1.867l2 1.241c1.897 1.178 2.846 1.766 3.365 2.715S22 9.915 22 12.203v1.522c0 3.9 0 5.851-1.172 7.063S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.212S2 17.626 2 13.725z" />
                                <path stroke-linecap="round" d="M12 15v3" />
                            </g>
                        </svg>
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
            :variant="activeMetric === 'value' ? 'tonal' : 'elevated'" elevation="2" @click="activeMetric = 'value'">
            <v-card-text class="pa-5">
                <div class="d-flex align-center ga-4">
                    <v-btn icon color="secondary" variant="elevated" elevation="0" density="default">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <g fill="none" stroke="currentColor" stroke-width="1.5">
                                <path
                                    d="M2 14c0-3.771 0-5.657 1.172-6.828S6.229 6 10 6h4c3.771 0 5.657 0 6.828 1.172S22 10.229 22 14s0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14Zm14-8c0-1.886 0-2.828-.586-3.414S13.886 2 12 2s-2.828 0-3.414.586S8 4.114 8 6" />
                                <path stroke-linecap="round"
                                    d="M12 17.333c1.105 0 2-.746 2-1.666S13.105 14 12 14s-2-.746-2-1.667c0-.92.895-1.666 2-1.666m0 6.666c-1.105 0-2-.746-2-1.666m2 1.666V18m0-8v.667m0 0c1.105 0 2 .746 2 1.666" />
                            </g>
                        </svg>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5">
                                <path
                                    d="M11 2c-4.055.007-6.178.107-7.536 1.464C2 4.928 2 7.285 2 11.999s0 7.071 1.464 8.536C4.93 21.999 7.286 21.999 12 21.999s7.071 0 8.535-1.464c1.358-1.357 1.457-3.48 1.464-7.536" />
                                <path stroke-linejoin="round" d="m13 11l9-9m0 0h-5.344M22 2v5.344M21 3l-9 9m0 0h4m-4 0V8" />
                            </g>
                        </svg>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <g fill="none" stroke="currentColor" stroke-width="1.5">
                                <path
                                    d="M4.979 9.685C2.993 8.891 2 8.494 2 8s.993-.89 2.979-1.685l2.808-1.123C9.773 4.397 10.767 4 12 4s2.227.397 4.213 1.192l2.808 1.123C21.007 7.109 22 7.506 22 8s-.993.89-2.979 1.685l-2.808 1.124C14.227 11.603 13.233 12 12 12s-2.227-.397-4.213-1.191z" />
                                <path
                                    d="m5.766 10l-.787.315C2.993 11.109 2 11.507 2 12s.993.89 2.979 1.685l2.808 1.124C9.773 15.603 10.767 16 12 16s2.227-.397 4.213-1.191l2.808-1.124C21.007 12.891 22 12.493 22 12s-.993-.89-2.979-1.685L18.234 10" />
                                <path
                                    d="m5.766 14l-.787.315C2.993 15.109 2 15.507 2 16s.993.89 2.979 1.685l2.808 1.124C9.773 19.603 10.767 20 12 20s2.227-.397 4.213-1.192l2.808-1.123C21.007 16.891 22 16.494 22 16c0-.493-.993-.89-2.979-1.685L18.234 14" />
                            </g>
                        </svg>
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
                            
                            <v-col cols="12" md="6">
                                <h3 class="card-title mb-1">
                                    {{ mainGraphTitle }}
                                </h3>
                                
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
                            
                            <v-col cols="12" md="6" class="d-flex justify-md-end align-start">
                                <v-btn-toggle
                                    v-model="selectedPeriod"
                                    variant="outlined"
                                    density="compact"
                                    color="primary"
                                    mandatory
                                >
                                    <v-btn size="small" value="1M" @click="setPeriod('1M')">1M</v-btn>
                                    <v-btn size="small" value="3M" @click="setPeriod('3M')">3M</v-btn>
                                    <v-btn size="small" value="6M" @click="setPeriod('6M')">6M</v-btn>
                                    <v-btn size="small" value="YTD" @click="setPeriod('YTD')">YTD</v-btn>
                                </v-btn-toggle>
                            </v-col>

                        </v-row>
                    </v-card-title>
                    <v-divider></v-divider>

                    <v-card-text style="min-height: 365px">
                        <v-skeleton-loader v-if="loading" type="image" height="350"></v-skeleton-loader>

                        <VueApexCharts v-else-if="!loading && selectedMonthlyChartLabels.length > 0" :options="chartOptions"
                            :series="mainGraphSeries" height="350" :key="activeMetric" />
                        <div v-else-if="!loading && selectedMonthlyChartLabels.length === 0"
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
                            ข้อมูลเปรียบเทียบรายเดือน (YOY, YTD, QoQ)
                        </h3>
                        <h5 class="card-subtitle " style="text-align: left;">
                             {{ chartUnitSubtitle }}
                        </h5>
                    </v-card-title>
                    <v-divider></v-divider>

                    <v-card-text>
                        <v-data-table-virtual 
                            :headers="monthlyComparisonTableHeaders" 
                            :items="monthlyComparisonTableData"
                            :loading="loadingRegional" :items-per-page="-1" 
                            class="elevation-0 text-grey" 
                            density="compact"
                            :key="activeMetric" >
                            
                            <template v-slot:item.month="{ item }">
                                <span class="font-weight-bold ">{{ item.month }}</span>
                            </template>
                            
                            <template v-slot:item.cy_value="{ item }">
                                <span class="text-end d-block text-grey">{{ item.cy_value.toLocaleString('th-TH', {
                                    maximumFractionDigits: activeMetric === 'units' ? 0 : 2,
                                    minimumFractionDigits: activeMetric === 'units' ? 0 : 2
                                }) }}</span>
                            </template>
                            
                            <template v-slot:item.py_value="{ item }">
                                <span class="text-end d-block text-grey">{{ item.py_value.toLocaleString('th-TH', {
                                    maximumFractionDigits: activeMetric === 'units' ? 0 : 2,
                                    minimumFractionDigits: activeMetric === 'units' ? 0 : 2
                                }) }}</span>
                            </template>

                            <template v-slot:item.yoy_percent="{ item }">
                                <span v-if="item.yoy_percent !== 0" :class="['font-weight-bold', getPercentageColor(item.yoy_percent)]">
                                    {{ formatPercentage(item.yoy_percent) }}
                                </span>
                                <span v-else class="text-grey">-</span>
                            </template>

                            <template v-slot:item.mom_percent="{ item }">
                                <span v-if="item.mom_percent !== 0" :class="['font-weight-bold', getPercentageColor(item.mom_percent)]">
                                    {{ formatPercentage(item.mom_percent) }}
                                </span>
                                <span v-else class="text-grey">-</span>
                            </template>
                            
                            <template v-slot:item.qoq_percent="{ item }">
                                <span v-if="item.qoq_percent !== null && item.qoq_percent !== 0" :class="['font-weight-bold', getPercentageColor(item.qoq_percent)]">
                                    {{ formatPercentage(item.qoq_percent) }}
                                </span>
                                <span v-else class="text-grey">-</span>
                            </template>

                             <template v-slot:item.cytd="{ item }">
                                <span class="text-end d-block text-grey" >{{ item.cytd.toLocaleString('th-TH', {
                                    maximumFractionDigits: activeMetric === 'units' ? 0 : 2,
                                    minimumFractionDigits: activeMetric === 'units' ? 0 : 2
                                }) }}</span>
                            </template>

                             <template v-slot:item.pytd="{ item }">
                                <span class="text-end d-block text-grey" >{{ item.pytd.toLocaleString('th-TH', {
                                    maximumFractionDigits: activeMetric === 'units' ? 0 : 2,
                                    minimumFractionDigits: activeMetric === 'units' ? 0 : 2
                                }) }}</span>
                            </template>

                            <template v-slot:item.ytd_percent="{ item }">
                                <span v-if="item.ytd_percent !== 0" :class="['font-weight-bold', getPercentageColor(item.ytd_percent)]" >
                                    {{ formatPercentage(item.ytd_percent) }}
                                </span>
                                <span v-else class="text-grey" style="background-color: #f5f5f5;">-</span>
                            </template>


                            <template v-slot:no-data>
                                <div class="pa-4 text-center text-grey">
                                    ไม่พบข้อมูลรายเดือนสำหรับตัวกรองที่คุณเลือก
                                </div>
                            </template>
                            
                             <template v-slot:bottom>
                                <div style="height: 0;"></div>
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