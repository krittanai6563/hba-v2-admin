<script setup lang="ts">
import defaultAvatar from '@/assets/images/users/img-logo_0.png';
import { ref, onMounted, computed, watch } from 'vue';
import { PyramidIcon } from 'vue-tabler-icons';
import * as XLSX from 'xlsx';
import ExcelJS from 'exceljs';
import type { BorderStyle, Cell } from 'exceljs';


const memberTypes = ref(['สามัญ', 'วิสามัญ ก', 'สมทบ']);
const selectedMemberTypeForm = ref('สามัญ');
const currentUserRole = ref<string | null>(null);

const fullname = ref('');
const email = ref('');
const password = ref('');
const show1 = ref(false);
const placetext = ref('');
const items = ref(['user', 'admin']);
const selectedRole = ref('user');

const rules = ref({
    required: (value: string) => !!value || 'Required.',
    min: (v: string) => v.length >= 8 || 'Min 8 characters',
    emailMatch: () => "The email and password you entered don't match"
});

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const message = ref('');
const showMessageBox = ref(false);

const selectedFile = ref<File | null>(null);

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


const filterMemberTypes = ref(['สามัญ', 'วิสามัญ ก', 'สมทบ']);
const roles = ref(['user', 'admin']);
const statuses = ref(['กรอกข้อมูลเรียบร้อย', 'ยังไม่กรอกข้อมูล']);

const selectedMemberTypeFilter = ref('ทั้งหมด');
const selectedRoleFilter = ref('');
const selectedStatusFilter = ref('ทั้งหมด');


const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;
const monthString = currentMonth < 10 ? `0${currentMonth}` : `${currentMonth}`;

interface MonthItem {
    value: string;
    label: string;
}

const monthNames = [
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

const months = ref<MonthItem[]>([]);
for (let m = 1; m <= currentMonth; m++) {
    const monthString = m < 10 ? `0${m}` : `${m}`;
    months.value.push({
        value: `${currentYear}-${monthString}`,
        label: monthNames[m - 1]
    });
}

const selectedMonthFilter = ref(`${currentYear}-${monthString}`);
const selectedYearFilter = ref<string>((currentYear + 543).toString());

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

const fetchMembers = async () => {
    try {
        let url = 'https://uat.hba-sales.org/backend/get_members.php';
        const requestOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }

        };


        if (selectedYearFilter.value && selectedMonthFilter.value) {

            const [year, month] = selectedMonthFilter.value.split('-');
            const buddhistYear = parseInt(year) + 543;
            const monthNumber = parseInt(month);

            url = `https://uat.hba-sales.org/backend/get_members.php?buddhist_year=${buddhistYear}&month_number=${monthNumber}`;
        } else if (selectedYearFilter.value) {

            const buddhistYear = parseInt(selectedYearFilter.value) + 543;
            url = `https://uat.hba-sales.org/backend/get_members.php?buddhist_year=${buddhistYear}`;
        }


        if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
            url += `&member_type=${selectedMemberTypeFilter.value}`;
        }


        if (selectedStatusFilter.value && selectedStatusFilter.value !== 'ทั้งหมด') {
            url += `&status=${selectedStatusFilter.value}`;
        }

        console.log('Fetching URL: ', url);

        const res = await fetch(url, requestOptions);
        if (!res.ok) throw new Error('Failed to fetch members');
        const data = await res.json();
        members.value = data;

        console.log('Members:', members.value);
    } catch (error) {
        console.error(error);
    }
};

watch([selectedYearFilter, selectedMonthFilter], () => {
    fetchMembers();
});

onMounted(() => {
    currentUserRole.value = localStorage.getItem('role');
    fetchMembers();
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
        const data = JSON.parse(text);
        // --- การแจ้งเตือนเมื่อสำเร็จ ---
        snackbarText.value = data.message || 'อัปเดตโปรไฟล์สำเร็จ!';
        snackbarColor.value = 'success';
        snackbar.value = true;
        
        isActive.value = false; 
        await fetchMembers();  
    } else {
        // --- การแจ้งเตือนเมื่อเซิร์ฟเวอร์มีปัญหา ---
        snackbarText.value = `อัปเดตล้มเหลว: ${text}`;
        snackbarColor.value = 'error';
        snackbar.value = true;
    }
} catch (error: unknown) {
    // --- การแจ้งเตือนเมื่อ Network หรือการแปลงข้อมูลมีปัญหา ---
    if (error instanceof Error) {
        snackbarText.value = 'อัปเดตล้มเหลว: ' + error.message;
    } else {
        snackbarText.value = 'อัปเดตล้มเหลว: เกิดข้อผิดพลาดที่ไม่คาดคิด';
    }
    snackbarColor.value = 'error';
    snackbar.value = true;
}
}

function closeMessageBox() {
    showMessageBox.value = false;
    message.value = '';
}


async function exportToExcel() {
    const workbook = new ExcelJS.Workbook();

    // --- (ส่วนนี้เหมือนเดิม: จัดกลุ่มข้อมูล) ---
    const groupedByType: { [key: string]: Member[] } = {};
    members.value.forEach((member) => {
        if (!groupedByType[member.member_type]) {
            groupedByType[member.member_type] = [];
        }
        groupedByType[member.member_type].push(member);
    });

    // --- (ส่วนนี้เหมือนเดิม: คำนวณสรุปผลรวม) ---
    let totalMembersOverall = members.value.length;
    let filledMembersOverall = members.value.filter((m) => m.status === 'กรอกข้อมูลเรียบร้อย').length;
    let missingMembersOverall = members.value.filter((m) => m.status !== 'กรอกข้อมูลเรียบร้อย').length;

    // --- (ส่วนนี้เหมือนเดิม: เตรียมข้อความหัวตาราง เดือน/ปี) ---
    let headerYearDisplay = 'ไม่ระบุปี';
    let headerMonthDisplay = 'ไม่ระบุเดือน';

    if (selectedYearFilter.value) {
        headerYearDisplay = `ปี ${selectedYearFilter.value}`;
    } else {
        headerYearDisplay = `ปี ${new Date().getFullYear() + 543}`;
    }

    if (selectedMonthFilter.value) {
        const [year, monthNum] = selectedMonthFilter.value.split('-');
        const monthIndex = parseInt(monthNum) - 1;
        headerMonthDisplay = `เดือน${monthNames[monthIndex]}`;
    } else {
        headerMonthDisplay = `เดือน${monthNames[new Date().getMonth()]}`;
    }

    // --- (ส่วนนี้เหมือนเดิม: ตั้งค่า font และเส้นขอบ) ---
    const angsanaNewFont = { name: 'Angsana New', family: 2, size: 12 };

    const thinBlackBorder = {
        top: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } },
        bottom: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } },
        left: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } },
        right: { style: 'thin' as BorderStyle, color: { argb: 'FF000000' } }
    };

    // --- [ปรับปรุง] ส่วนที่ 1: สร้างชีต "สมาชิกทั้งหมด" (4 คอลัมน์) ---
    const allWorksheet = workbook.addWorksheet('สมาชิกทั้งหมด');
    
    // หัวข้อหลัก
    const titleText = `ข้อมูลสมาชิกที่กรอกข้อมูลประจำ${headerMonthDisplay} ${headerYearDisplay}`;
    allWorksheet.addRow([titleText]);
    allWorksheet.mergeCells('A1:D1'); // 4 คอลัมน์
    const titleCell = allWorksheet.getCell('A1');
    titleCell.font = { ...angsanaNewFont, bold: true, size: 16 };
    titleCell.alignment = { vertical: 'middle', horizontal: 'center' };

    // หัวข้อรอง
    allWorksheet.addRow(['สรุปสมาชิกทั้งหมด']);
    allWorksheet.mergeCells('A2:D2'); // 4 คอลัมน์
    allWorksheet.getCell('A2').font = { ...angsanaNewFont, bold: true, size: 14 };
    allWorksheet.getCell('A2').alignment = { vertical: 'middle', horizontal: 'left' };

    allWorksheet.addRow([]); // แถวที่ 3

    // หัวข้อตาราง
    allWorksheet.addRow(['ที่', 'บริษัท', 'ประเภทสมาชิก', headerYearDisplay]); // เพิ่ม 'ประเภทสมาชิก'
    const allHeaderRow4 = allWorksheet.getRow(4);
    allHeaderRow4.font = { ...angsanaNewFont, bold: true };
    allHeaderRow4.alignment = { vertical: 'middle', horizontal: 'center' };
    allHeaderRow4.eachCell({ includeEmpty: true }, (cell: Cell) => {
        cell.border = thinBlackBorder;
    });

    allWorksheet.addRow(['', '', '', headerMonthDisplay]); // เพิ่มช่องว่าง
    const allHeaderRow5 = allWorksheet.getRow(5);
    allHeaderRow5.font = { ...angsanaNewFont, bold: true };
    allHeaderRow5.alignment = { vertical: 'middle', horizontal: 'center' };
    allHeaderRow5.eachCell({ includeEmpty: true }, (cell: Cell) => {
        cell.border = thinBlackBorder;
    });

    // Merge หัวข้อตาราง
    allWorksheet.mergeCells('A4:A5');
    allWorksheet.mergeCells('B4:B5');
    allWorksheet.mergeCells('C4:C5'); // Merge คอลัมน์ใหม่
    allWorksheet.mergeCells('D4:D5'); // คอลัมน์สถานะเลื่อนเป็น D

    // วนลูปข้อมูล "สมาชิกทั้งหมด"
    members.value.forEach((member, index) => {
        const statusIndicator = member.status === 'กรอกข้อมูลเรียบร้อย' ? '✓' : '';
        const row = allWorksheet.addRow([
            index + 1,
            member.fullname,
            member.member_type, // เพิ่มข้อมูลประเภท
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
                    fgColor: { argb: 'FFFF9999' } // สีแดงอ่อน
                };
            } else {
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: 'FFFFFFFF' }
                };
            }
            
            if (cell.col === 'D') { // จัดกลางคอลัมน์ D (สถานะ)
                cell.alignment = { vertical: 'middle', horizontal: 'center' };
            }
        });
    });

    // แถวสรุป (สำหรับชีต "สมาชิกทั้งหมด")
    allWorksheet.addRow([]);
    const totalRowAll = allWorksheet.addRow(['รวม', '', '', totalMembersOverall]);
    allWorksheet.mergeCells(totalRowAll.getCell(1).address + ':' + totalRowAll.getCell(3).address); // Merge A-C
    totalRowAll.font = { ...angsanaNewFont, bold: true };
    totalRowAll.getCell(1).alignment = { horizontal: 'right' };
    totalRowAll.getCell(4).alignment = { horizontal: 'center' }; // ข้อมูลรวม ở คอลัมน์ D
    totalRowAll.eachCell({ includeEmpty: true }, (cell: Cell) => {
        cell.border = thinBlackBorder;
    });

    const missingRowAll = allWorksheet.addRow(['ยังไม่กรอกข้อมูล', '', '', missingMembersOverall]);
    allWorksheet.mergeCells(missingRowAll.getCell(1).address + ':' + missingRowAll.getCell(3).address); // Merge A-C
    missingRowAll.font = { ...angsanaNewFont, bold: true };
    missingRowAll.getCell(1).alignment = { horizontal: 'right' };
    missingRowAll.getCell(4).alignment = { horizontal: 'center' }; // ข้อมูลรวม ở คอลัมน์ D
    missingRowAll.eachCell({ includeEmpty: true }, (cell: Cell) => {
        cell.border = thinBlackBorder;
    });

    // ตั้งค่าความกว้างคอลัมน์ (สำหรับชีต "สมาชิกทั้งหมด")
    allWorksheet.columns = [
        { key: 'ลำดับ', width: 10 },
        { key: 'ชื่อบริษัท', width: 30 },
        { key: 'ประเภทสมาชิก', width: 20 }, // คอลัมน์ใหม่
        { key: 'สถานะเดือน', width: 25 }
    ];

    // เพิ่มสรุปผลรวมทั้งหมด (เฉพาะในชีต "สมาชิกทั้งหมด")
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
    // --- [จบส่วนชีต "สมาชิกทั้งหมด"] ---


    // --- [ปรับปรุง] ส่วนที่ 2: วนลูปสร้างชีตตามประเภท (3 คอลัมน์) ---
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

        worksheet.addRow([]); // แถวที่ 3

        // หัวข้อตาราง
        worksheet.addRow(['ที่', 'บริษัท', headerYearDisplay]);
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
                        fgColor: { argb: 'FFFF9999' } // สีแดงอ่อน
                    };
                } else {
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FFFFFFFF' } // สีขาว
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
    // --- [จบส่วนวนลูปตามประเภท] ---


    // --- [ปรับปรุง] ส่วนที่ 3: สร้างและดาวน์โหลดไฟล์ (พร้อมชื่อไฟล์แบบไดนามิก) ---
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;

    // --- สร้างชื่อไฟล์แบบไดนามิก ---
    let fileName = 'members_report';

    if (selectedYearFilter.value) {
        // selectedYearFilter.value คือปี พ.ศ. เช่น "2568"
        fileName += `_${selectedYearFilter.value}`;
    }

    if (selectedMonthFilter.value && selectedYearFilter.value) { 
        // selectedMonthFilter.value คือ "2025-11"
        const [year, monthNum] = selectedMonthFilter.value.split('-');
        const monthIndex = parseInt(monthNum) - 1;
        // monthNames[monthIndex] คือชื่อเดือน เช่น "พฤศจิกายน"
        fileName += `_${monthNames[monthIndex]}`;
    }

    // เพิ่มนามสกุล .xlsx
    fileName += '.xlsx';
    
    // --- ใช้ชื่อไฟล์ใหม่ ---
    a.download = fileName;
    
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<template>
     <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="top right">
        {{ snackbarText }}
        <template v-slot:actions>
            <v-btn color="white" variant="text" @click="snackbar = false">
                ปิด
            </v-btn>
        </template>
    </v-snackbar>

    <v-row>
        <v-col cols="12" sm="12" lg="12">
            <VCard elevation="0">
                <v-card-text>
                    <div class="v-row">
                        <div class="v-col-md-6 v-col-12">
                            <div class="d-flex align-center">
                                <div>
                                    <h3 class="card-title mb-1">ลงทะเบียนสมาชิก</h3>
                                    <h5 class="card-subtitle" style="text-align: left">จัดการข้อมูลสมาชิกสมาคม</h5>
                                </div>
                            </div>
                        </div>

                        <div class="v-col-md-6 v-col-12 text-right" >
                            <div class="d-flex justify-end v-col-md-12 v-col-lg-12 v-col-12" >
                                <v-btn-group color="#b2d7ef" density="comfortable" rounded="pill" divided v-if="currentUserRole === 'master'">
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

                                <!-- Adjust margin between the buttons -->
                                <v-btn class="text-primary v-btn--size-small ml-4" @click="exportToExcel">
                                    <div class="text-none font-weight-regular muted">Export to CSV</div>
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <br /><br />

                    <v-row class="mb-4" dense>
                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedYearFilter" :items="years" label="ประจำปี" dense clearable
                                :menu-props="{ maxHeight: '200' }" />
                        </v-col>

                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedMonthFilter" :items="months" label="เดือน" dense clearable
                                item-title="label" item-value="value" :menu-props="{ maxHeight: '200' }"
                                :disabled="!selectedYearFilter" />
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

                    <div class="v-card-text">
                        <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
                            <div class="v-table__wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="text-subtitle-1 font-weight-medium ps-2">สมาชิก</th>
                                            <th class="text-subtitle-1 font-weight-medium">อีเมล</th>
                                            <th class="text-subtitle-1 font-weight-medium">สถานะ</th>
                                            <!-- <th class="text-subtitle-1 font-weight-medium text-end">มูลค่ายอดเซ็นสัญญา</th> -->
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
                                                    <v-avatar size="48">
                                                        <img :src="getProfileImageUrl(member.profile_image)" alt="user"
                                                            style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover"
                                                            @error="
                                                                (event: Event) => {
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
                                                <h5 class="text-no-wrap text-subtitle-1 textSecondary">{{ member.email
                                                    }}</h5>
                                            </td>
                                            <td>
                                                <v-chip
                                                    :color="member.status === 'กรอกข้อมูลเรียบร้อย' ? 'success' : 'error'"
                                                    label small class="ma-2 rounded-pill">
                                                    {{ member.status }}
                                                </v-chip>
                                            </td>
                    
                                            <td>
                                                <v-dialog max-width="500">
                                                    <template v-slot:activator="{ props: activatorProps }">
                                                        <v-btn v-bind="activatorProps" variant="flat"
                                                            @click="openEditDialog(member.id)">
                                                            <i class="mdi-pencil mdi v-icon notranslate v-theme--BLUE_THEME v-icon--size-small text-info v-icon--clickable me-2"
                                                                role="button" aria-hidden="false"
                                                                tabindex="0"></i></v-btn>
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
                                                                            <v-select v-model="selectedMemberTypeForm"
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

                                                <!-- แสดงข้อความแจ้งเตือน -->
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
