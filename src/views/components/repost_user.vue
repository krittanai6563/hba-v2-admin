ตามคำขอของคุณ ผมได้ปรับปรุงโค้ด `repost_user.vue` ให้ครอบคลุมทั้ง 3 ส่วนหลักที่ร้องขอ ดังนี้:

1.  **ช่องเดือนแสดงชื่อเดือน:** แก้ไขการกำหนดค่าเริ่มต้นของ `selectedMonthFilter` ให้ตรงกับ `value` ในรายการ `months` (ซึ่งคือปี ค.ศ. และเดือน) เพื่อให้ `v-select` สามารถแสดงผลเป็น **ชื่อเดือน** ได้อย่างถูกต้อง
2.  **ความสวยงามของกราฟ:** เปลี่ยนสีเป็น **น้ำเงิน-เหลือง** และปรับขนาดฟอนต์ให้ใหญ่ขึ้น พร้อมทั้งใช้เส้นกริดแบบเส้นปะ
3.  **รายงาน Excel/PDF พร้อมกราฟ:** ปรับการฝังกราฟในรายงานให้มี **สัดส่วนที่พอดี** และแก้ไขข้อผิดพลาดที่เกิดขึ้น

### โค้ดที่ปรับปรุง: `repost_user.vue` (ฉบับสมบูรณ์)

```vue
<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import defaultAvatar from '@/assets/images/users/img-logo_0.png';

// ********** 1. Imports สำหรับ Report **********
import ExcelJS from 'exceljs';
import type { BorderStyle, Cell } from 'exceljs';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import Apexchart from 'vue3-apexcharts'; 

// ********** 2. State สำหรับ Report **********
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const exportLoading = ref(false);

// ********** State สำหรับ Chart **********
interface MonthlyChartData {
    label: string;
    submitted: number;
    notSubmitted: number;
    totalMembers: number;
}
interface ChartSeriesItem { 
    name: string; 
    data: number[]; 
}

const chartData = ref<MonthlyChartData[]>([]);
const totalMembersInDatabase = ref(0);
const chartOptions = ref({});
const chartSeries = ref<ChartSeriesItem[]>([]);
const chartRef = ref(null); // Ref to access the chart instance for image export
// **********************************************

// *** กำหนดประเภทสมาชิกที่ต้องการนับในกราฟเท่านั้น ***
const coreMemberTypesForChart = ['สมทบ', 'สามัญ', 'วิสามัญ ก'];

const memberTypes = ref(['สมทบ', 'สามัญ', 'วิสามัญ ก']);
const selectedMemberTypeForm = ref('วิสามัญ');

const fullname = ref('');
const email = ref('');
const password = ref('');
const show1 = ref(false);
const placetext = ref('');
const items = ref(['user', 'admin']);
const selectedRole = ref('user');

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;
const currentBuddhistYear = (currentYear + 543).toString();
const currentMonthString = currentMonth < 10 ? `0${currentMonth}` : `${currentMonth}`;
// *** Point 1 Fix: ใช้ YYYY (ค.ศ.) ในค่าเริ่มต้นของเดือน เพื่อให้ตรงกับ item-value ***
const initialMonthFilterValueGregorian = `${currentYear}-${currentMonthString}`; 

const rules = ref({
    required: (value: string) => !!value || 'Required.',
    min: (v: string) => v.length >= 8 || 'Min 8 characters',
    emailMatch: () => "The email and password you entered don't match"
});

const message = ref('');
const showMessageBox = ref(false);

const selectedFile = ref<File | null>(null);

// ********** 3. Helper Function สำหรับ PDF **********
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

const generateChartImage = async (): Promise<string | null> => {
    if (chartRef.value && chartRef.value.chart && chartRef.value.chart.dataURI) {
        try {
            const dataUri = await chartRef.value.chart.dataURI();
            return dataUri.imgURI;
        } catch (e) {
            console.error("Error generating chart image URI:", e);
            return null;
        }
    }
    return null;
};
// *************************************************

const handleFileUpload = () => {
    if (selectedFile.value) {
        console.log('File selected:', selectedFile.value);
    } else {
        message.value = 'กรุณาอัพโหลดไฟล์รูปภาพเท่านั้น!';
    }
};

async function register() {
    message.value = '';
    showMessageBox.value = false;
    try {
        const formData = new FormData();
        formData.append('fullname', fullname.value);
        formData.append('email', email.value);
        formData.append('password', password.value);
        formData.append('role', selectedRole.value);
        formData.append('member_type', selectedMemberTypeForm.value);

        if (selectedFile.value) {
            formData.append('profile_image', selectedFile.value);
        }

        const res = await fetch('https://uat.hba-sales.org/backend/register.php', {
            method: 'POST',
            body: formData
        });

        const text = await res.text();
        console.log(text);

        if (res.ok) {
            try {
                let data = JSON.parse(text);
                message.value = data.message || 'Registration successful!';
            } catch (e) {
                message.value = 'Error: Invalid JSON format';
            }
        } else {
            message.value = `Registration failed: ${text}`;
        }
    } catch (error: unknown) {
        if (error instanceof Error) {
            message.value = 'Registration failed: ' + error.message;
        } else {
            message.value = 'Registration failed: An unexpected error occurred.';
        }
    } finally {
        showMessageBox.value = true;
    }
}

interface Member {
    id: number;
    email: string;
    password: string;
    fullname: string | null;
    member_type: string;
    submitted_at?: string;
    updated_at?: string;
    role: string | null;
    profile_image: string | null;
    status: string;
    contractValue: number;
}

const members = ref<Member[]>([]);

const filterMemberTypes = ref(['วิสามัญ', 'วิสามัญ ก', 'สมทบ', 'จัดการข้อมูล', 'ผู้ดูแลระบบ']);
const roles = ref(['user', 'admin']);
const statuses = ref(['กรอกข้อมูลเรียบร้อย', 'ยังไม่กรอกข้อมูล']);

const selectedMemberTypeFilter = ref('');
const selectedRoleFilter = ref('');
const selectedStatusFilter = ref('');


interface MonthItem {
    value: string;
    label: string;
}

// *** Point 1 Fix: กำหนดค่าเริ่มต้นเป็น YYYY(ค.ศ.)-MM เพื่อให้ v-select แสดงผลเป็นชื่อเดือนได้
const selectedMonthFilter = ref<string[] | string>([initialMonthFilterValueGregorian]); 
const selectedYearFilter = ref<string[] | string>([currentBuddhistYear]);

const filteredMembers = computed(() => {
    let filtered = members.value;
    if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
        filtered = filtered.filter((member) => member.member_type === selectedMemberTypeFilter.value);
    }

    if (selectedStatusFilter.value && selectedStatusFilter.value !== 'ทั้งหมด') {
        filtered = filtered.filter((member) => member.status === selectedStatusFilter.value);
    }

    return filtered;
});

// *** Computed Property: Member Types ที่ใช้จริงในการคำนวณกราฟ (Point 1) ***
const effectiveMemberTypes = computed(() => {
    if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
        // หากผู้ใช้เลือกประเภทสมาชิกเฉพาะ ให้ใช้เฉพาะรายการนั้น
        return [selectedMemberTypeFilter.value];
    }
    // หากเลือก "ทั้งหมด" ให้ใช้ coreMemberTypesForChart ที่กำหนดไว้
    return coreMemberTypesForChart;
});


// *** Computed Property สำหรับ Title ของ VCard (Point 2) ***
const chartTitle = computed(() => {
    const labels = chartData.value.map(d => d.label);
    
    if (labels.length === 0) {
        return "สรุปสถานะการกรอกข้อมูลสมาชิก";
    }

    const filteredLabels = labels.filter(label => label !== 'สมาชิกทั้งหมด');

    if (filteredLabels.length === 0) {
         return "สรุปสถานะการกรอกข้อมูลสมาชิก";
    }

    const firstLabelParts = filteredLabels[0].split(' ');
    const lastLabelParts = filteredLabels[filteredLabels.length - 1].split(' ');

    const startMonth = firstLabelParts[0];
    const startYear = firstLabelParts[1];
    const endMonth = lastLabelParts[0];
    const endYear = lastLabelParts[1];

    const selectedFilterType = selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด' 
        ? ` (ประเภท: ${selectedMemberTypeFilter.value})` : '';

    if (startMonth === endMonth && startYear === endYear) {
         return `กราฟเปรียบเทียบจำนวนสมาชิก ประจำเดือน ${startMonth} ${startYear}${selectedFilterType}`;
    }

    if (startYear === endYear) {
        return `กราฟเปรียบเทียบจำนวนสมาชิก ประจำเดือน ${startMonth} - ${endMonth} ปี ${startYear}${selectedFilterType}`;
    }

    return `กราฟเปรียบเทียบจำนวนสมาชิก ตั้งแต่ ${startMonth} ${startYear} ถึง ${endMonth} ${endYear}${selectedFilterType}`;
});


// *** ฟังก์ชันที่ถูกแก้ไข: ดึงข้อมูลสมาชิกทั้งหมด (กรองตาม effectiveMemberTypes) ***
const fetchTotalMembers = async () => {
    try {
        let url = 'https://uat.hba-sales.org/backend/get_members_master.php'; 
        
        const res = await fetch(url, { method: 'POST' });
        if (!res.ok) throw new Error('Failed to fetch total members count');
        const data: Member[] = await res.json();
        
        // กรองเฉพาะประเภทสมาชิกที่ต้องการนับเป็นฐานของกราฟ
        const filteredData = data.filter(m => effectiveMemberTypes.value.includes(m.member_type));
        
        totalMembersInDatabase.value = filteredData.length > 0 ? filteredData.length : 7; 
    } catch (error) {
        console.error("Fetch total members error:", error);
        totalMembersInDatabase.value = 7; 
    }
};

// *** ฟังก์ชันที่ถูกแก้ไข: อัปเดตกราฟ (ปรับปรุง Aesthetics) ***
const updateChartConfig = () => {
    if (chartData.value.length === 0) {
        chartSeries.value = [];
        return;
    }

    const categories = chartData.value.map(d => d.label);
    const submitted = chartData.value.map(d => d.submitted);
    const notSubmitted = chartData.value.map(d => d.notSubmitted);
    
    // 1. เพิ่มแท่ง "สมาชิกทั้งหมด" ที่จุดเริ่มต้น
    const totalCount = totalMembersInDatabase.value || (chartData.value.length > 0 ? chartData.value[0].totalMembers : 0);
    const initialCategory = 'สมาชิกทั้งหมด';
    categories.unshift(initialCategory);
    submitted.unshift(0); 
    notSubmitted.unshift(totalCount); 

    chartSeries.value = [
        {
            name: 'สมาชิกที่กรอกข้อมูล',
            data: submitted
        },
        {
            name: 'สมาชิกที่ไม่ได้กรอกข้อมูล',
            data: notSubmitted
        }
    ] as ChartSeriesItem[]; 

    chartOptions.value = {
        chart: {
            type: 'bar',
            height: 350,
            stacked: true,
            toolbar: { show: false },
            fontFamily: 'TH Sarabun New, sans-serif', // กำหนดฟอนต์ภาษาไทย
        },
        plotOptions: {
            bar: {
                horizontal: true,
                dataLabels: {
                    total: {
                        enabled: true,
                        offsetX: 0,
                        style: {
                            fontSize: '18px', // ปรับขนาดฟอนต์ให้ใหญ่ขึ้น
                            fontWeight: 900
                        }
                    }
                }
            },
        },
        grid: {
            show: true,
            borderColor: '#E0E0E0',
            strokeDashArray: 3, // เส้นกริดเป็นเส้นปะ
            xaxis: {
                lines: { show: true }
            },
            yaxis: {
                lines: { show: false }
            }
        },
        stroke: { width: 1, colors: ['#fff'] },
        xaxis: {
            categories: categories,
            title: { text: 'จำนวนสมาชิก' },
            labels: { style: { fontFamily: 'TH Sarabun New, sans-serif', fontSize: '18px' } }, // ปรับขนาดฟอนต์ X-axis
            axisBorder: { show: false }, 
            axisTicks: { show: false }
        },
        yaxis: { 
            title: { text: undefined },
            labels: { style: { fontFamily: 'TH Sarabun New, sans-serif', fontSize: '18px' } }, // ปรับขนาดฟอนต์ Y-axis
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        tooltip: { y: { formatter: (val: number) => val.toFixed(0) } },
        fill: { opacity: 1 },
        legend: { position: 'top', horizontalAlign: 'left', offsetX: 40 },
        colors: ['#1976D2', '#FFC107'] // สี: น้ำเงิน (Submitted) และ เหลือง (Not Submitted)
    };
};

// *** ฟังก์ชันที่ถูกแก้ไข: ดึงข้อมูลสำหรับกราฟ (Multi-month/Multi-year) ***
const fetchChartData = async () => {
    // 1. Determine Years to query
    const yearsForProcessing = Array.isArray(selectedYearFilter.value)
        ? selectedYearFilter.value.filter(y => y) 
        : (selectedYearFilter.value ? [selectedYearFilter.value] : []);
    
    // 2. Determine Months to query
    const selectedMonthValues = Array.isArray(selectedMonthFilter.value)
        ? selectedMonthFilter.value.filter(m => m)
        : (selectedMonthFilter.value ? [selectedMonthFilter.value] : []);

    if (yearsForProcessing.length === 0 || selectedMonthValues.length === 0) {
        chartData.value = [];
        updateChartConfig();
        return;
    }
    
    // ต้องเรียก fetchTotalMembers อีกครั้ง หากมีการเปลี่ยน filter Member Type
    await fetchTotalMembers();


    const promises = [];
    
    // Iterate through all combinations of selected Years and Months
    for (const displayYear of yearsForProcessing) {
        for (const monthYearValue of selectedMonthValues) {
            
            const [year, monthNumStr] = monthYearValue.split('-'); // monthYearValue is in YYYY-MM format
            const monthNumber = parseInt(monthNumStr);
            const monthIndex = monthNumber - 1;
            const monthLabel = monthNames[monthIndex];
            
            const targetBuddhistYear = parseInt(displayYear); 

            let url = `https://uat.hba-sales.org/backend/get_members_master.php?buddhist_year=${targetBuddhistYear}&month_number=${monthNumber}`;

            promises.push((async () => {
                try {
                    const res = await fetch(url, { method: 'POST' });
                    if (!res.ok) throw new Error(`Failed to fetch data for ${monthLabel} ${targetBuddhistYear}`);
                    const data: Member[] = await res.json();
                    
                    // กรอง Submitted members โดยใช้ effectiveMemberTypes
                    const submitted = data.filter(m => 
                        m.status === 'กรอกข้อมูลเรียบร้อย' && 
                        effectiveMemberTypes.value.includes(m.member_type)
                    ).length;
                    
                    const total = totalMembersInDatabase.value; 
                    const notSubmitted = total - submitted;

                    return {
                        label: `${monthLabel} ${targetBuddhistYear}`,
                        submitted: submitted,
                        notSubmitted: notSubmitted,
                        totalMembers: total,
                    };
                } catch (error) {
                    console.error(`Error fetching data for ${monthLabel} ${targetBuddhistYear}:`, error);
                    return null;
                }
            })());
        }
    }


    const results = (await Promise.all(promises)).filter((r): r is MonthlyChartData => r !== null);
    // Sort the results: first by year, then by month index, for proper display order
    results.sort((a, b) => {
        const yearA = parseInt(a.label.split(' ')[1]);
        const yearB = parseInt(b.label.split(' ')[1]);
        const monthIndexA = monthNames.findIndex(m => a.label.includes(m));
        const monthIndexB = monthNames.findIndex(m => b.label.includes(m));

        if (yearA !== yearB) return yearA - yearB;
        return monthIndexA - monthIndexB;
    });

    chartData.value = results;
    updateChartConfig();
};


const fetchMembers = async () => {
    try {
        // ใช้ logic เดิม แต่เน้นดึงข้อมูลสำหรับเดือน/ปีแรกที่เลือกเท่านั้นสำหรับตาราง
        const monthFilterValue = Array.isArray(selectedMonthFilter.value) ? selectedMonthFilter.value[0] : selectedMonthFilter.value;
        const yearFilterValue = Array.isArray(selectedYearFilter.value) ? selectedYearFilter.value[0] : selectedYearFilter.value;

        if (!yearFilterValue || !monthFilterValue) {
            members.value = [];
            return;
        }

        const [year, month] = monthFilterValue.split('-');
        let buddhistYear = parseInt(yearFilterValue); 
        const isBuddhistYear = buddhistYear > 2500;

        if (!isBuddhistYear) {
            buddhistYear += 543;
        }

        const monthNumber = parseInt(month.split('-')[1] || month); 
        if (isNaN(monthNumber)) return; 

        let url = `https://uat.hba-sales.org/backend/get_members_master.php?buddhist_year=${buddhistYear}&month_number=${monthNumber}`;

        if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
            url += `&member_type=${selectedMemberTypeFilter.value}`;
        }

        if (selectedStatusFilter.value && selectedStatusFilter.value !== 'ทั้งหมด') {
            url += `&status=${selectedStatusFilter.value}`;
        }

        console.log("Fetching URL for table: ", url);

        const res = await fetch(url, {
            method: 'POST',  
        });

        if (!res.ok) {
            throw new Error('Failed to fetch members');
        }

        const data = await res.json();
        members.value = data;

    } catch (error) {
        console.error("Fetch table error:", error);
        message.value = "An error occurred while fetching the members. Please try again later.";
    }
};

// Refetch members & chart data whenever filters are updated
watch([selectedYearFilter, selectedMonthFilter, selectedMemberTypeFilter, selectedStatusFilter], () => {
    fetchMembers();
    fetchChartData(); 
});

// Call fetchMembers when component is mounted
onMounted(async () => {
    await fetchTotalMembers();
    fetchMembers();
    fetchChartData();
});


function getProfileImageUrl(path: string | null): string {
    if (!path || path.trim() === '') {
        return defaultAvatar;
    }
    return `https://uat.hba-sales.org/backend/${path}`;
}

function formatCurrency(value: number | string): string {
    const numberValue = Number(value);
    if (isNaN(numberValue)) return '-';
    return numberValue.toLocaleString('th-TH', { style: 'currency', currency: 'THB' });
}


const months = ref<MonthItem[]>([]);

const monthNames = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];

// ใช้ currentYear (ค.ศ.) เพื่อสร้างค่า value ที่เป็นมาตรฐาน YYYY-MM
for (let m = 1; m <= 12; m++) {
    const monthString = m < 10 ? `0${m}` : `${m}`;
    months.value.push({
        value: `${currentYear}-${monthString}`, 
        label: monthNames[m - 1]
    });
}

console.log(months.value);

const years = ref<string[]>([]);


for (let i = 0; i < 7; i++) {
    const buddhistYear = currentYear - i + 543;
    years.value.push(buddhistYear.toString());
}


const dialogActive = ref(false);
let memberId: number | null = null;

function openEditDialog(memberIdParam: number) {
    memberId = memberIdParam;
    const selectedMember = members.value.find((member) => member.id === memberId);
    if (selectedMember) {
        fullname.value = selectedMember.fullname || '';
        email.value = selectedMember.email || '';
        selectedMemberTypeForm.value = selectedMember.member_type || '';
        selectedRole.value = selectedMember.role || 'user';
    }
    dialogActive.value = true;
}

async function updateMember(isActive: { value: boolean }) {
    if (!memberId) {
        message.value = 'No member selected for update.';
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', memberId.toString());
        formData.append('fullname', fullname.value);
        formData.append('email', email.value);
        formData.append('role', selectedRole.value);
        formData.append('member_type', selectedMemberTypeForm.value);

        if (selectedFile.value) {
            formData.append('profile_image', selectedFile.value);
        }

        const res = await fetch('https://uat.hba-sales.org/backend/edit_profile.php', {
            method: 'POST',
            body: formData
        });

        const text = await res.text();
        console.log(text);

        if (res.ok) {
            try {
                let data = JSON.parse(text);
                message.value = data.message || 'Profile updated successfully!';
                showMessageBox.value = true;
                isActive.value = false;

                setTimeout(() => {
                    showMessageBox.value = false;
                }, 3000);

                await fetchMembers();
            } catch (e) {
                message.value = 'Error: Invalid JSON format';
            }
        } else {
            message.value = `Update failed: ${text}`;
        }
    } catch (error: unknown) {
        if (error instanceof Error) {
            message.value = 'Update failed: ' + error.message;
        } else {
            message.value = 'Update failed: An unexpected error occurred.';
        }
    }
}

function closeMessageBox() {
    showMessageBox.value = false;
    message.value = '';
}

const router = useRouter();

const goToPostRepostUser = (memberId1: number) => {

    const monthFilterValue = Array.isArray(selectedMonthFilter.value) ? selectedMonthFilter.value[0] : selectedMonthFilter.value;
    const yearFilterValue = Array.isArray(selectedYearFilter.value) ? selectedYearFilter.value[0] : selectedYearFilter.value;

    if (!yearFilterValue || !monthFilterValue) {
        alert("กรุณาเลือกปีและเดือนก่อน");
        return;
    }

    let buddhistYear = parseInt(yearFilterValue);
    const isBuddhistYear = buddhistYear > 2500;

    if (!isBuddhistYear) {
        buddhistYear += 543;
    }

    const monthNumber = parseInt(monthFilterValue.split('-')[1]);


    router.push({
        name: 'post_repost_user',
        query: {
            memberId1,
            buddhist_year: buddhistYear,
            month_number: monthNumber,
        }
    });
};


async function exportToExcel() {
    // 1. ตั้งค่าสถานะเริ่มต้น
    exportLoading.value = true;
    snackbar.value = false;

    if (members.value.length === 0) {
        exportLoading.value = false;
        snackbarText.value = 'ไม่พบข้อมูลสมาชิกที่กรองไว้เพื่อสร้างรายงาน Excel';
        snackbarColor.value = 'warning';
        snackbar.value = true;
        return;
    }

    try {
        const workbook = new ExcelJS.Workbook();
        const chartDataURI = await generateChartImage(); // Generate Chart Image

        // 2. เตรียมข้อมูล (ใช้โค้ดเดิม)
        const groupedByType: { [key: string]: Member[] } = {};
        members.value.forEach((member) => {
            if (!groupedByType[member.member_type]) {
                groupedByType[member.member_type] = [];
            }
            groupedByType[member.member_type].push(member);
        });

        let totalMembersOverall = members.value.length;
        let filledMembersOverall = members.value.filter((m) => m.status === 'กรอกข้อมูลเรียบร้อย').length;
        let missingMembersOverall = members.value.filter((m) => m.status !== 'กรอกข้อมูลเรียบร้อย').length;

        // ใช้ค่าแรกที่ถูกเลือกสำหรับแสดงในหัวตาราง/ชื่อไฟล์
        const reportYear = Array.isArray(selectedYearFilter.value) ? selectedYearFilter.value[0] : selectedYearFilter.value;
        const reportMonth = Array.isArray(selectedMonthFilter.value) ? selectedMonthFilter.value[0] : selectedMonthFilter.value;
        
        let headerYearDisplay = reportYear ? `ปี ${reportYear}` : `ปี ${new Date().getFullYear() + 543}`;

        let headerMonthDisplay = reportMonth ? 
            `เดือน${monthNames[parseInt(reportMonth.split('-')[1]) - 1]}` : 
            `เดือน${monthNames[new Date().getMonth()]}`;
        
        const titleText = `ข้อมูลสมาชิกที่กรอกข้อมูลประจำ${headerMonthDisplay} ${headerYearDisplay}`;
        
        const angsanaNewFont = { name: 'Angsana New', family: 2, size: 12 };

        const thinBlackBorder = {
            top: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } },
            bottom: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } },
            left: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } },
            right: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } }
        };

        // 3. ชีต "สมาชิกทั้งหมด"
        const allWorksheet = workbook.addWorksheet('สรุปภาพรวม');
        
        // ********** EMBED CHART **********
        // เริ่มตารางที่แถว 28
        let startRow = 28;

        if (chartDataURI) {
             const imageId = workbook.addImage({
                base64: chartDataURI.split(',')[1], // ExcelJS needs raw Base64 data
                extension: 'png',
            });
            // วางรูปภาพไว้ที่ A1 ถึง F25 (col 0 ถึง 5)
            allWorksheet.addImage(imageId, {
                tl: { col: 0, row: 0 },
                br: { col: 5, row: 25 }, // *** FIX: Adjusted boundary for better aspect ratio ***
            });

            // ปรับความสูงแถวเพื่อรองรับกราฟ (row 1-25)
            for (let i = 1; i <= 25; i++) {
                allWorksheet.getRow(i).height = 15; 
            }
        }
        // **********************************

        // หัวข้อหลักของตาราง
        allWorksheet.addRow([titleText]);
        allWorksheet.mergeCells(`A${startRow}:D${startRow}`); 
        const titleCell = allWorksheet.getCell(`A${startRow}`);
        titleCell.font = { ...angsanaNewFont, bold: true, size: 16 };
        titleCell.alignment = { vertical: 'middle', horizontal: 'center' };

        // หัวข้อรอง
        allWorksheet.addRow(['สรุปสมาชิกทั้งหมด']);
        allWorksheet.mergeCells(`A${startRow + 1}:D${startRow + 1}`); 
        allWorksheet.getCell(`A${startRow + 1}`).font = { ...angsanaNewFont, bold: true, size: 14 };
        allWorksheet.getCell(`A${startRow + 1}`).alignment = { vertical: 'middle', horizontal: 'left' };

        allWorksheet.addRow([]);

        // หัวข้อตาราง
        allWorksheet.addRow(['ที่', 'บริษัท', 'ประเภทสมาชิก', headerMonthDisplay]); 
        const allHeaderRow4 = allWorksheet.getRow(startRow + 3);
        allHeaderRow4.font = { ...angsanaNewFont, bold: true };
        allHeaderRow4.alignment = { vertical: 'middle', horizontal: 'center' };
        allHeaderRow4.eachCell({ includeEmpty: true }, (cell: Cell) => {
            cell.border = thinBlackBorder;
        });

        allWorksheet.addRow(['', '', '', headerMonthDisplay]); 
        const allHeaderRow5 = allWorksheet.getRow(startRow + 4);
        allHeaderRow5.font = { ...angsanaNewFont, bold: true };
        allHeaderRow5.alignment = { vertical: 'middle', horizontal: 'center' };
        allHeaderRow5.eachCell({ includeEmpty: true }, (cell: Cell) => {
            cell.border = thinBlackBorder;
        });

        // Merge หัวข้อตาราง
        allWorksheet.mergeCells(`A${startRow + 3}:A${startRow + 4}`);
        allWorksheet.mergeCells(`B${startRow + 3}:B${startRow + 4}`);
        allWorksheet.mergeCells(`C${startRow + 3}:C${startRow + 4}`);
        allWorksheet.mergeCells(`D${startRow + 3}:D${startRow + 4}`); 

        // วนลูปข้อมูล "สมาชิกทั้งหมด"
        members.value.forEach((member, index) => {
            const statusIndicator = member.status === 'กรอกข้อมูลเรียบร้อย' ? '✓' : '';
            const row = allWorksheet.addRow([
                index + 1,
                member.fullname,
                member.member_type, 
                statusIndicator
            ]);

            row.eachCell({ includeEmpty: true }, (cell: Cell) => {
                cell.alignment = { vertical: 'middle', horizontal: 'left' };
                cell.font = angsanaNewFont;
                cell.border = thinBlackBorder;

                if (member.status === 'ยังไม่กรอกข้อมูล') {
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FFFF9999' } 
                    };
                } else {
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FFFFFFFF' }
                    };
                }
                
                if (cell.col === 'D') { 
                    cell.alignment = { vertical: 'middle', horizontal: 'center' };
                }
            });
        });

        // แถวสรุป
        allWorksheet.addRow([]);
        const totalRowAll = allWorksheet.addRow(['รวม', '', '', totalMembersOverall]);
        allWorksheet.mergeCells(totalRowAll.getCell(1).address + ':' + totalRowAll.getCell(3).address); 
        totalRowAll.font = { ...angsanaNewFont, bold: true };
        totalRowAll.getCell(1).alignment = { horizontal: 'right' };
        totalRowAll.getCell(4).alignment = { horizontal: 'center' }; 
        totalRowAll.eachCell({ includeEmpty: true }, (cell: Cell) => {
            cell.border = thinBlackBorder;
        });

        const missingRowAll = allWorksheet.addRow(['ยังไม่กรอกข้อมูล', '', '', missingMembersOverall]);
        allWorksheet.mergeCells(missingRowAll.getCell(1).address + ':' + missingRowAll.getCell(3).address); 
        missingRowAll.font = { ...angsanaNewFont, bold: true };
        missingRowAll.getCell(1).alignment = { horizontal: 'right' };
        missingRowAll.getCell(4).alignment = { horizontal: 'center' }; 
        missingRowAll.eachCell({ includeEmpty: true }, (cell: Cell) => {
            cell.border = thinBlackBorder;
        });

        // ตั้งค่าความกว้างคอลัมน์
        allWorksheet.columns = [
            { key: 'ลำดับ', width: 10 },
            { key: 'ชื่อบริษัท', width: 30 },
            { key: 'ประเภทสมาชิก', width: 20 }, 
            { key: 'สถานะเดือน', width: 25 }
        ];

        // เพิ่มสรุปผลรวมทั้งหมด (ด้านล่างตาราง)
        const bufferRows = 5;
        for (let i = 0; i < bufferRows; i++) {
            allWorksheet.addRow([]);
        }

        const filledLabelRow = allWorksheet.addRow(['', '', 'กรอกข้อมูลแล้ว', filledMembersOverall]);
        filledLabelRow.getCell(3).alignment = { horizontal: 'right' };
        filledLabelRow.getCell(4).alignment = { horizontal: 'center' };
        filledLabelRow.getCell(3).font = { ...angsanaNewFont, bold: true };
        filledLabelRow.getCell(3).border = thinBlackBorder;
        filledLabelRow.getCell(4).border = thinBlackBorder;

        const missingLabelRow = allWorksheet.addRow(['', '', 'ยังไม่กรอกข้อมูล', missingMembersOverall]);
        missingLabelRow.getCell(3).alignment = { horizontal: 'right' };
        missingLabelRow.getCell(4).alignment = { horizontal: 'center' };
        missingLabelRow.getCell(3).font = { ...angsanaNewFont, bold: true };
        missingLabelRow.getCell(3).border = thinBlackBorder;
        missingLabelRow.getCell(4).border = thinBlackBorder;


        // 4. วนลูปสร้างชีตตามประเภท
        for (const memberType of Object.keys(groupedByType)) {
            const worksheet = workbook.addWorksheet(memberType);

            // หัวข้อหลัก
            worksheet.addRow([titleText]);
            worksheet.mergeCells('A1:C1');
            const typeTitleCell = worksheet.getCell('A1');
            typeTitleCell.font = { ...angsanaNewFont, bold: true, size: 16 };
            typeTitleCell.alignment = { vertical: 'middle', horizontal: 'center' };

            // หัวข้อรอง (ประเภทสมาชิก)
            worksheet.addRow([memberType]);
            worksheet.mergeCells('A2:C2');
            worksheet.getCell('A2').font = { ...angsanaNewFont, bold: true, size: 14 };
            worksheet.getCell('A2').alignment = { vertical: 'middle', horizontal: 'left' };

            worksheet.addRow([]); 

            // หัวข้อตาราง
            worksheet.addRow(['ที่', 'บริษัท', headerMonthDisplay]);
            const headerRow4 = worksheet.getRow(4);
            headerRow4.font = { ...angsanaNewFont, bold: true };
            headerRow4.alignment = { vertical: 'middle', horizontal: 'center' };
            headerRow4.eachCell({ includeEmpty: true }, (cell: Cell) => {
                cell.border = thinBlackBorder;
            });

            worksheet.addRow(['', '', headerMonthDisplay]);
            const headerRow5 = worksheet.getRow(5);
            headerRow5.font = { ...angsanaNewFont, bold: true };
            headerRow5.alignment = { vertical: 'middle', horizontal: 'center' };
            headerRow5.eachCell({ includeEmpty: true }, (cell: Cell) => {
                cell.border = thinBlackBorder;
            });

            worksheet.mergeCells('A4:A5');
            worksheet.mergeCells('B4:B5');
            worksheet.mergeCells('C4:C5');

            // วนลูปข้อมูล (เฉพาะประเภท)
            let totalMembersInType = 0;
            let missingMembersInType = 0;

            groupedByType[memberType].forEach((member, index) => {
                const statusIndicator = member.status === 'กรอกข้อมูลเรียบร้อย' ? '✓' : '';
                const row = worksheet.addRow([index + 1, member.fullname, statusIndicator]);

                row.eachCell({ includeEmpty: true }, (cell: Cell) => {
                    cell.alignment = { vertical: 'middle', horizontal: 'left' };
                    cell.font = angsanaNewFont;
                    cell.border = thinBlackBorder;

                    if (member.status === 'ยังไม่กรอกข้อมูล') {
                        cell.fill = {
                            type: 'pattern',
                            pattern: 'solid',
                            fgColor: { argb: 'FFFF9999' } 
                        };
                    } else {
                        cell.fill = {
                            type: 'pattern',
                            pattern: 'solid',
                            fgColor: { argb: 'FFFFFFFF' } 
                        };
                    }

                    if (cell.col === 'C') {
                        cell.alignment = { vertical: 'middle', horizontal: 'center' };
                    }
                });

                totalMembersInType++;
                if (member.status !== 'กรอกข้อมูลเรียบร้อย') {
                    missingMembersInType++;
                }
            });

            // แถวสรุป (เฉพาะประเภท)
            worksheet.addRow([]);
            const totalRow = worksheet.addRow(['รวม', '', totalMembersInType]);
            worksheet.mergeCells(totalRow.getCell(1).address + ':' + totalRow.getCell(2).address);
            totalRow.font = { ...angsanaNewFont, bold: true };
            totalRow.getCell(1).alignment = { horizontal: 'right' };
            totalRow.getCell(3).alignment = { horizontal: 'center' };
            totalRow.eachCell({ includeEmpty: true }, (cell: Cell) => {
                cell.border = thinBlackBorder;
            });

            const missingRow = worksheet.addRow(['ยังไม่กรอกข้อมูล', '', missingMembersInType]);
            worksheet.mergeCells(missingRow.getCell(1).address + ':' + missingRow.getCell(2).address);
            missingRow.font = { ...angsanaNewFont, bold: true };
            missingRow.getCell(1).alignment = { horizontal: 'right' };
            missingRow.getCell(3).alignment = { horizontal: 'center' };
            missingRow.eachCell({ includeEmpty: true }, (cell: Cell) => {
                cell.border = thinBlackBorder;
            });

            // ตั้งค่าความกว้างคอลัมน์ (เฉพาะประเภท)
            worksheet.columns = [
                { key: 'ลำดับ', width: 10 },
                { key: 'ชื่อบริษัท', width: 30 },
                { key: 'สถานะเดือน', width: 25 }
            ];
        }


        // 5. สร้าง Buffer และดาวน์โหลดไฟล์
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;

        let fileName = 'members_report_repost';
        const singleYear = Array.isArray(selectedYearFilter.value) ? selectedYearFilter.value[0] : selectedYearFilter.value;
        const singleMonth = Array.isArray(selectedMonthFilter.value) ? selectedMonthFilter.value[0] : selectedMonthFilter.value;

        if (singleYear) {
            fileName += `_${singleYear}`;
        }

        if (singleMonth && singleYear) { 
            const [year, monthNum] = singleMonth.split('-');
            const monthIndex = parseInt(monthNum) - 1;
            fileName += `_${monthNames[monthIndex]}`;
        }

        fileName += '.xlsx';
        a.download = fileName;
        
        a.click();
        window.URL.revokeObjectURL(url);
        
        // แจ้งเตือนสำเร็จ
        snackbarText.value = 'สร้างไฟล์ Excel สำเร็จแล้ว';
        snackbarColor.value = 'success';
        snackbar.value = true;


    } catch (err: any) {
        // 6. จัดการข้อผิดพลาด
        console.error('Error exporting to Excel:', err);
        snackbarText.value = `ไม่สามารถสร้าง Excel ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุระหว่างการประมวลผล'}`;
        snackbarColor.value = 'error';
        snackbar.value = true;
    } finally {
        // 7. ปิดสถานะโหลด
        exportLoading.value = false;
    }
}


async function exportToPdf() {
    exportLoading.value = true;
    snackbar.value = false;

    if (members.value.length === 0) {
        exportLoading.value = false;
        snackbarText.value = 'ไม่พบข้อมูลสมาชิกที่กรองไว้เพื่อสร้างรายงาน PDF';
        snackbarColor.value = 'warning';
        snackbar.value = true;
        return;
    }

    try {
        // 1. โหลดทรัพยากร (ฟอนต์และโลโก้)
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
            throw new Error(`Font (Regular) loading failed: ${fontErr.message}`);
        }
        
        const fontBoldUrl = '/fonts/THSarabunNew Bold.ttf'; 
        let fontBoldBase64 = '';
        try {
            const fontResponse = await fetch(fontBoldUrl);
            if (!fontResponse.ok) throw new Error('Failed to fetch local font: /fonts/THSarabunNew Bold.ttf');
            const fontBlob = await fontResponse.blob();
            const fontDataURL = await blobToDataURL(fontBlob);
            fontBoldBase64 = fontDataURL.split(',')[1];
            if (!fontBoldBase64) throw new Error('Failed to parse Base64 from font Data URL');
        } catch (fontErr: any) {
            throw new Error(`Font (Bold) loading failed: ${fontErr.message}`);
        }
        
        // โหลดโลโก้ (อ้างอิงจาก user.vue)
        const logoUrl = '/assets/images/image-28-2.png'; 
        let logoDataURL = '';
        try {
            const logoResponse = await fetch(logoUrl);
            if (logoResponse.ok) {
                const logoBlob = await logoResponse.blob();
                logoDataURL = await blobToDataURL(logoBlob);
            } else {
                console.warn('Logo file not found. Skipping logo.');
            }
        } catch (imgErr) {
            console.error('Error fetching logo, skipping:', imgErr);
        }


        // 2. สร้าง PDF และตั้งค่าฟอนต์
        const pdf = new jsPDF('p', 'mm', 'a4');
        const fontName = 'THSarabunNew';
        
        pdf.addFileToVFS('THSarabunNew.ttf', atob(fontBase64));
        pdf.addFont('THSarabunNew.ttf', fontName, 'normal');
        
        pdf.addFileToVFS('THSarabunNew Bold.ttf', atob(fontBoldBase64));
        pdf.addFont('THSarabunNew Bold.ttf', fontName, 'bold');
        
        pdf.setFont(fontName, 'normal'); 

        // 3. เตรียมข้อมูล
        // ใช้ค่าแรกที่ถูกเลือกสำหรับแสดงในหัวตาราง/ชื่อไฟล์
        const reportYear = Array.isArray(selectedYearFilter.value) ? selectedYearFilter.value[0] : selectedYearFilter.value;
        const reportMonth = Array.isArray(selectedMonthFilter.value) ? selectedMonthFilter.value[0] : selectedMonthFilter.value;

        let headerYearDisplay = reportYear ? `ปี ${reportYear}` : `ปี ${new Date().getFullYear() + 543}`;
        
        let headerMonthDisplay = reportMonth ? 
            `เดือน${monthNames[parseInt(reportMonth.split('-')[1]) - 1]}` : 
            `เดือน${monthNames[new Date().getMonth()]}`;
            
        const titleText = `ข้อมูลสมาชิกที่กรอกข้อมูลประจำ${headerMonthDisplay} ${headerYearDisplay}`;
        
        const groupedByType: { [key: string]: Member[] } = {};
        members.value.forEach((member) => {
            if (!groupedByType[member.member_type]) {
                groupedByType[member.member_type] = [];
            }
            groupedByType[member.member_type].push(member);
        });
        
        let fileName = 'members_report_repost';
        if (reportYear) fileName += `_${reportYear}`;
        if (reportMonth && reportYear) {
            const [year, monthNum] = reportMonth.split('-');
            const monthIndex = parseInt(monthNum) - 1;
            fileName += `_${monthNames[monthIndex]}`;
        }

        // 4. สร้างหน้าปก
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
        
        pdf.setFont(fontName, 'bold'); 
        pdf.setFontSize(20);
        pdf.text(titleText, pageCenter, currentY, { align: 'center' });
        currentY += 10;
        
        pdf.setFont(fontName, 'normal'); 
        const today = new Date();
        const dateString = `อัพเดตข้อมูลวันที่ ${today.getDate()} ${monthNames[today.getMonth()]} ${today.getFullYear() + 543}`;
        pdf.setFontSize(15);
        pdf.text(dateString, pageCenter, currentY, { align: 'center' });


        // ********** EMBED CHART **********
        const chartDataURI = await generateChartImage();
        if (chartDataURI) {
             pdf.addPage(); // เพิ่มหน้าใหม่สำหรับกราฟ
             pdf.setFont(fontName, 'bold');
             pdf.setFontSize(18);
             pdf.text(chartTitle.value, pageWidth / 2, 20, { align: 'center' }); // ใช้ Chart Title ที่สร้างไว้
             
             // วางรูปภาพกราฟ
             const imgWidth = 180; 
             const imgHeight = imgWidth / 1.71; // *** FIX: คำนวณความสูงตามสัดส่วน 1.71:1 ***
             const imgX = (pageWidth - imgWidth) / 2;
             
             pdf.addImage(chartDataURI, 'PNG', imgX, 30, imgWidth, imgHeight);
             currentY = 30 + imgHeight + 10; // อัปเดต currentY
        }
        // **********************************

        // 5. สร้างชีต "สมาชิกทั้งหมด"
        pdf.addPage();
        pdf.setFont(fontName, 'bold');
        pdf.setFontSize(18);
        pdf.text("สรุปสมาชิกสมาคมกรอกยอดเซ็นสัญญาทั้งหมด", pageCenter, 20, { align: 'center' });
        pdf.setFont(fontName, 'normal');
        pdf.setFontSize(14);
        pdf.text(titleText, pageCenter, 28, { align: 'center' }); 
        
        const allHead = [['ที่', 'บริษัท', 'ประเภทสมาชิก', 'สถานะเดือน']];
        
        const allBody = members.value.map((member, index) => {
            const statusIndicator = member.status === 'กรอกข้อมูลเรียบร้อย' ? 'กรอกแล้ว' : 'ยังไม่กรอก';
            return [index + 1, member.fullname, member.member_type, statusIndicator];
        });

        autoTable(pdf, {
            head: allHead,
            body: allBody,
            startY: 30,
            styles: {
                font: fontName, 
                fontStyle: 'normal',
                fontSize: 10,
                cellPadding: 1,
            },
            headStyles: {
                font: fontName, 
                fontStyle: 'bold', 
                fillColor: [220, 220, 220], 
                textColor: [0, 0, 0]
            },
            columnStyles: {
                3: { halign: 'left' } 
            },
            didParseCell: (data) => {
                if (data.section === 'body' && data.column.index === 3 && data.cell.text.includes('ยังไม่กรอก')) {
                    data.cell.styles.textColor = [255, 0, 0]; // ตั้งค่าสีข้อความเป็นสีแดง
                }
            },
            willDrawCell: (data) => {
                if (data.section === 'body') {
                    // *** แก้ไข: Cast data.row.raw เป็น array เพื่อแก้ TS7053 ***
                    const rowData = data.row.raw as any[];
                    // ใช้ rowData[1] เพื่อเข้าถึง fullname (ดัชนี 1)
                    const member = members.value.find(m => m.fullname === rowData[1]); 
                    if (member && member.status === 'ยังไม่กรอกข้อมูล') {
                        pdf.setFillColor(255, 153, 153); // สีแดงอ่อน
                    }
                }
            }
        });


        // 6. สร้างชีต "แยกตามประเภท"
        for (const memberType of Object.keys(groupedByType)) {
            pdf.addPage();
            pdf.setFont(fontName, 'bold');
            pdf.setFontSize(18);
            pdf.text(`ประเภทสมาชิก ${memberType}`, pageCenter, 20, { align: 'center' }); 
            pdf.setFont(fontName, 'normal');
            pdf.setFontSize(14);
            pdf.text(titleText, pageCenter, 28, { align: 'center' }); 

            const typeHead = [['ที่', 'บริษัท', 'สถานะเดือน']];
            
            const typeBody = groupedByType[memberType].map((member, index) => {
                 const statusIndicator = member.status === 'กรอกข้อมูลเรียบร้อย' ? 'กรอกแล้ว' : 'ยังไม่กรอก';
                 return [index + 1, member.fullname, statusIndicator];
            });

            autoTable(pdf, {
                head: typeHead,
                body: typeBody,
                startY: 35,
                styles: {
                    font: fontName, 
                    fontStyle: 'normal',
                    fontSize: 10,
                    cellPadding: 1,
                },
                headStyles: {
                    font: fontName, 
                    fontStyle: 'bold', 
                    fillColor: [220, 220, 220],
                    textColor: [0, 0, 0]
                },
                columnStyles: {
                    2: { halign: 'left' }
                },
                didParseCell: (data) => {
                    if (data.section === 'body' && data.column.index === 2 && data.cell.text.includes('ยังไม่กรอก')) {
                        data.cell.styles.textColor = [255, 0, 0]; 
                    }
                },
                willDrawCell: (data) => {
                    if (data.section === 'body') {
                        // *** แก้ไข: Cast data.row.raw เป็น array เพื่อแก้ TS7053 ***
                        const rowData = data.row.raw as any[];
                        // ใช้ rowData[1] เพื่อเข้าถึง fullname (ดัชนี 1)
                        const member = groupedByType[memberType].find(m => m.fullname === rowData[1]);
                        if (member && member.status === 'ยังไม่กรอกข้อมูล') {
                            pdf.setFillColor(255, 153, 153); 
                        }
                    }
                }
            });
        }
        
        // 7. บันทึกไฟล์ (ปรับปรุง: เปิดในหน้าต่างใหม่เพื่อให้ผู้ใช้บันทึกเอง)
        const pdfBlob = pdf.output('blob');
        const pdfUrl = URL.createObjectURL(pdfBlob);

        window.open(pdfUrl, '_blank');
        
        // Cleanup
        window.URL.revokeObjectURL(pdfUrl);
        
        // แจ้งเตือนสำเร็จ
        snackbarText.value = 'สร้างไฟล์ PDF สำเร็จแล้ว กรุณาบันทึกจากหน้าต่างใหม่';
        snackbarColor.value = 'success';
        snackbar.value = true;


    } catch (err: any) {
        console.error('Error exporting to PDF:', err);
        snackbarText.value = `ไม่สามารถสร้าง PDF ได้: ${err.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}`;
        snackbarColor.value = 'error';
        snackbar.value = true;
    } finally {
        exportLoading.value = false;
    }
}
</script>

<template>
    <v-row>

        <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="top right">
        {{ snackbarText }}
        <template v-slot:actions>
            <v-btn color="white" variant="text" @click="snackbar = false">
                ปิด
            </v-btn>
        </template>
        </v-snackbar>

         <v-overlay
            :model-value="exportLoading"
            class="align-center justify-center"
            persistent
            scrim="white"
            style="opacity: 0.8;"
        >
            <div class="text-center">
                <v-progress-circular
                    color="primary"
                    indeterminate
                    size="64"
                ></v-progress-circular>
                <h4 class="text-primary mt-3">กำลังสร้างไฟล์รายงาน...</h4>
            </div>
        </v-overlay>

        <v-col cols="12" sm="12" lg="12">
            <VCard elevation="0">
                <v-card-text>

                    <div class="v-row">
                        <div class="v-col-md-6 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">ลงทะเบียนสมาชิก</h3>
                                    <h5 class="card-subtitle" style="text-align: left;">จัดการข้อมูลสมาชิกสมาคม</h5>
                                </div>
                            </div>
                        </div>

                        <div class="v-col-md-6 v-col-12 text-right">
    <div class="d-flex justify-end v-col-md-12 v-col-lg-12 v-col-12">
        
        <v-btn-group color="#b2d7ef" density="comfortable" rounded="pill" divided class="mr-3">
            <v-btn
                class="v-btn v-btn--flat v-theme--BLUE_THEME bg-primary v-btn--density-default v-btn--rounded v-btn--size-default">
                <div class="text-none font-weight-regular primary">เพิ่มข้อมูลสมาชิกสมาคม</div>
                <v-dialog activator="parent" max-width="800">
                    <template v-slot:default="{ isActive }">
                        <v-card rounded="lg">
                            <v-card-title
                                class="d-flex justify-space-between align-center ps-5 py-5">
                                <div class="text-h5 text-medium-emphasis ps-2">
                                    <h3 class="text-h3 mb-1">ลงทะเบียนสมาชิก</h3>
                                    <div class="text-subtitle-1 opacity-80"
                                        style="font-weight: 300">
                                        เพิ่มข้อมูลสมาชิกสมาคม
                                    </div>
                                </div>
                                <v-btn icon="mdi-close" variant="text"
                                    @click="isActive.value = false"></v-btn>
                            </v-card-title>
                            <v-divider class="mb-4"></v-divider>
                            <v-card-text>
                                <form @submit.prevent="register">
                                    <v-row>
                                        <v-col cols="12" sm="6">
                                            <v-label class="mb-1">ชื่อบริษัท</v-label>
                                            <v-text-field id="fullname" v-model="fullname"
                                                variant="outlined" hide-details
                                                color="primary"></v-text-field>
                                        </v-col>

                                        <v-col cols="12" sm="6">
                                            <v-label class="mb-1">ประเภทสมาชิก</v-label>
                                            <v-select :items="memberTypes"
                                                v-model="selectedMemberTypeForm" />
                                        </v-col>

                                        <v-col cols="12" sm="6">
                                            <v-label class="mb-1">อีเมล</v-label>
                                            <v-text-field variant="outlined" type="email"
                                                hide-details color="primary" id="email"
                                                v-model="email"></v-text-field>
                                        </v-col>

                                        <v-col cols="12" sm="6">
                                            <v-label class="mb-1">รหัสผ่าน</v-label>
                                            <v-text-field id="password" v-model="password"
                                                :append-icon="show1 ? 'mdi-eye' : 'mdi-eye-off'"
                                                :rules="[rules.required, rules.min]"
                                                :type="show1 ? 'text' : 'password'"
                                                name="input-10-1" hint="At least 8 characters"
                                                counter @click:append="show1 = !show1"
                                                hide-details></v-text-field>
                                        </v-col>

                                        <v-col cols="12">
                                            <v-label
                                                class="mb-1">สิทธิการเข้าถึงข้อมูล</v-label>
                                            <v-select :items="items" v-model="selectedRole"
                                                hide-details></v-select>
                                        </v-col>

                                        <v-col cols="12">
                                            <v-label class="mb-1">อัพโหลดรูปโปรไฟล์</v-label>
                                            <v-file-input label="File input"
                                                v-model="selectedFile" accept="image/*"
                                                @change="handleFileUpload"></v-file-input>
                                        </v-col>

                                        <v-col cols="12">
                                            <v-btn type="submit" color="primary" size="large"
                                                block flat class="w-100">
                                                สมัครสมาชิก
                                            </v-btn>
                                        </v-col>
                                    </v-row>
                                </form>

                                <p v-if="message" class="mt-3">{{ message }}</p>
                            </v-card-text>

                            <v-divider class="mt-2"></v-divider>
                        </v-card>
                    </template>
                </v-dialog>
            </v-btn>
        </v-btn-group>
        
        <v-btn-group color="#b2d7ef" density="comfortable" rounded="pill" divided>
            <v-btn color="success" @click="exportToExcel">
                <v-icon start>mdi-file-excel</v-icon>
                <div class="text-none font-weight-regular">รายงาน Excel</div>
            </v-btn>
            <v-btn color="error" @click="exportToPdf" :loading="exportLoading">
                    <v-icon start>mdi-file-pdf-box</v-icon>
                <div class="text-none font-weight-regular">รายงาน PDF</div>
            </v-btn>
        </v-btn-group>
        
    </div>
</div>
                    </div>
                    <br><br>

                    <v-row class="mb-4">
                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedYearFilter" :items="years" label="ประจำปี" dense clearable
                                :menu-props="{ maxHeight: '200' }" 
                                multiple />

                        </v-col>

                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedMonthFilter" :items="months" label="เดือน" dense clearable
                                item-title="label" item-value="value" :disabled="!selectedYearFilter"
                                :menu-props="{ maxHeight: '200' }" 
                                multiple />

                        </v-col>

                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedMemberTypeFilter" :items="['ทั้งหมด', ...filterMemberTypes]"
                                label="ประเภทสมาชิก" dense clearable :menu-props="{ maxHeight: '200' }" />
                        </v-col>

                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedStatusFilter" :items="['ทั้งหมด', ...statuses]" label="สถานะ"
                                dense clearable :menu-props="{ maxHeight: '200' }" />
                        </v-col>

                    </v-row>
                    
                    <v-row class="mt-8">
                        <v-col cols="12">
                            <div class="" >
                                
                                <h4 class="text-h6 mb-4">{{ chartTitle }}</h4>
                                

                                <div class="v-card-text pa-0">
                                    <div v-if="chartSeries.length > 0">
                                        <Apexchart 
                                            ref="chartRef" 
                                            type="bar" 
                                            height="350" 
                                            :options="chartOptions" 
                                            :series="chartSeries"
                                        ></Apexchart>
                                    </div>
                                    <div v-else class="text-center py-5">
                                        กรุณาเลือกปีและเดือนอย่างน้อยหนึ่งรายการเพื่อแสดงกราฟ
                                    </div>
                                </div>
                            </div>
                        </v-col>
                    </v-row>
                    <br/>
                    
                    <br/>
                    <div class="v-card-text">
                        <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                            <div class="v-table__wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="text-subtitle-1 font-weight-medium ps-2">สมาชิก</th>
                                            <th class="text-subtitle-1 font-weight-medium">สถานะ</th>
                                            <th class="text-subtitle-1 font-weight-medium text-end">มูลค่า</th>
                                            <th class="text-subtitle-1 font-weight-medium"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="filteredMembers.length === 0">
                                            <td colspan="4" class="text-center text-subtitle-1 py-4">
                                                ไม่พบข้อมูลมูลสมาชิก</td>
                                        </tr>
                                        <tr v-else v-for="(member, index) in filteredMembers" :key="index">
                                            <td class="ps-0 py-3">
                                                <div class="d-flex align-center">
                                                    <v-avatar size="30">
                                                        <img :src="getProfileImageUrl(member.profile_image)" alt="user"
                                                            style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover"
                                                            @error="
                                                                (event) => {
                                                                    const target = event.target as HTMLImageElement;
                                                                    if (target) {
                                                                        target.src = defaultAvatar;
                                                                    }
                                                                }
                                                            " />
                                                    </v-avatar>
                                                    <div class="mx-4">
                                                        <h4 class="text-16 text-no-wrap font-weight-medium">{{
                                                            member.fullname }}</h4>
                                                        <span class="text-subtitle-1" style="color: red">{{
                                                            member.member_type }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <v-chip
                                                    :color="member.status === 'กรอกข้อมูลเรียบร้อย' ? 'success' : 'error'"
                                                    label small class="ma-2 rounded-pill">
                                                    {{ member.status }}
                                                </v-chip>
                                            </td>
                                            <td class="text-end">
                                                <h4 class="text-no-wrap text-subtitle-1 textSecondary">{{
                                                    formatCurrency(member.contractValue) }}</h4>
                                            </td>

                                            <td>
                                                <div class="d-flex align-center">

                                                    <v-dialog max-width="600">
                                                        <template v-slot:activator="{ props: activatorProps }">
                                                            <v-btn v-bind="activatorProps" variant="flat"> <button
                                                                    type="button" @click="openEditDialog(member.id)"
                                                                    class="v-btn v-btn--flat v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                                                    aria-describedby="v-tooltip-884"><span
                                                                        class="v-btn__overlay"></span><span
                                                                        class="v-btn__underlay"></span><span
                                                                        class="v-btn__content" data-no-activator=""><svg
                                                                            xmlns="http://www.w3.org/2000/svg"
                                                                            class="icon-tabler icon-tabler-pencil text-primary"
                                                                            width="20px" height="20px"
                                                                            viewBox="0 0 24 24" stroke-width="1.5"
                                                                            stroke="currentColor" fill="none"
                                                                            stroke-linecap="round"
                                                                            stroke-linejoin="round">
                                                                            <path stroke="none" d="M0 0h24v24H0z"
                                                                                fill="none">
                                                                            </path>
                                                                            <path
                                                                                d="M4 20h4l10.5 -10.5a1.5 1.5 0 0 0 -4 -4l-10.5 10.5v4">
                                                                            </path>
                                                                            <path d="M13.5 6.5l4 4"></path>
                                                                        </svg></span></button></v-btn>

                                                        </template>

                                                        <template v-slot:default="{ isActive }">
                                                            <v-card rounded="lg">
                                                                <v-card-title> แก้ไขข้อมูลสมาชิก </v-card-title>
                                                                <v-card-text>
                                                                    <form @submit.prevent="updateMember(isActive)">
                                                                        <v-row>
                                                                            <v-col cols="12" sm="6">
                                                                                <v-label>ชื่อบริษัท</v-label>
                                                                                <v-text-field v-model="fullname"
                                                                                    readonly></v-text-field>
                                                                            </v-col>
                                                                            <v-col cols="12" sm="6">
                                                                                <v-label>ประเภทสมาชิก</v-label>
                                                                                <v-select
                                                                                    v-model="selectedMemberTypeForm"
                                                                                    :items="memberTypes"></v-select>
                                                                            </v-col>
                                                                            <v-col cols="12">
                                                                                <v-label>อีเมล</v-label>
                                                                                <v-text-field v-model="email"
                                                                                    readonly></v-text-field>
                                                                            </v-col>
                                                                            <v-col cols="12">
                                                                                <v-label>สิทธิการเข้าถึงข้อมูล</v-label>
                                                                                <v-select v-model="selectedRole"
                                                                                    :items="items"></v-select>
                                                                            </v-col>
                                                                            <v-col cols="12">
                                                                                <v-btn type="submit"
                                                                                    color="primary">บันทึกการแก้ไข</v-btn>
                                                                            </v-col>
                                                                        </v-row>
                                                                    </form>
                                                                </v-card-text>
                                                            </v-card>
                                                        </template>
                                                    </v-dialog>

                                                    <button @click="goToPostRepostUser(member.id)" type="button"
                                                        class="v-btn v-btn--flat v-btn--icon v-theme--BLUE_THEME v-btn--density-default v-btn--size-default v-btn--variant-elevated"
                                                        aria-describedby="v-tooltip-886"><span
                                                            class="v-btn__overlay"></span><span
                                                            class="v-btn__underlay"></span><span
                                                            class="v-btn__content" data-no-activator=""><svg
                                                                xmlns="http://www.w3.org/2000/svg" width="18"
                                                                height="18" viewBox="0 0 24 24">
                                                                <path fill="#001dff" fill-rule="evenodd"
                                                                    d="M11.5 2.75a8.75 8.75 0 1 0 0 17.5a8.75 8.75 0 0 0 0-17.5M1.25 11.5c0-5.66 4.59-10.25 10.25-10.25S21.75 5.84 21.75 11.5c0 2.56-.939 4.902-2.491 6.698l3.271 3.272a.75.75 0 1 1-1.06 1.06l-3.272-3.271A10.2 10.2 0 0 1 11.5 21.75c-5.66 0-10.25-4.59-10.25-10.25"
                                                                    clip-rule="evenodd" />
                                                            </svg></span></button>
                                                </div>

                                                <v-dialog v-model="showMessageBox" max-width="400px">
                                                    <v-card>
                                                        <v-card-title class="text-h6">แจ้งเตือน</v-card-title>
                                                        <v-card-text>{{ message }}</v-card-text>
                                                        <v-card-actions>
                                                            <v-btn color="primary" text
                                                                @click="closeMessageBox">ปิด</v-btn>
                                                        </v-card-actions>
                                                    </v-card>
                                                </v-dialog>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </v-card-text>
            </VCard>
        </v-col>
    </v-row>
</template>



<style scoped>
.container {
    max-width: 600px;
    margin: auto;
}

p {
    color: red;
    text-align: center;
}

.h1 {
    font-size: 12px;
    max-width: inherit;
}
</style>
