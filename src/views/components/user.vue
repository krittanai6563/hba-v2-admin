<script setup lang="ts">
import defaultAvatar from '@/assets/images/users/img-logo_0.png';
import { ref, onMounted, computed, watch } from 'vue';
import { PyramidIcon } from 'vue-tabler-icons';
import * as XLSX from 'xlsx';

// ฟอร์มสมัครสมาชิก
const memberTypes = ref(['วิสามัญ', 'วิสามัญ ก', 'สมทบ']);
const selectedMemberTypeForm = ref('วิสามัญ');

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

        const res = await fetch('https://6e9fdf451a56.ngrok-free.app/package/backend/register.php', {
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

// ฟิลเตอร์สำหรับตารางสมาชิก
const filterMemberTypes = ref(['วิสามัญ', 'วิสามัญ ก', 'สมทบ']);
const roles = ref(['user', 'admin']);
const statuses = ref(['กรอกข้อมูลเรียบร้อย', 'ยังไม่กรอกข้อมูล']);

const selectedMemberTypeFilter = ref('');
const selectedRoleFilter = ref('');
const selectedStatusFilter = ref('');

// เดือนและปีปัจจุบัน
const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

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

const selectedMonthFilter = ref(''); 
const selectedYearFilter = ref<string>('');  // สร้างตัวแปรเป็น string

const filteredMembers = computed(() => {
    let filtered = members.value;

    // กรองตามประเภทสมาชิก
    if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
        filtered = filtered.filter((member) => member.member_type === selectedMemberTypeFilter.value);
    }

    // กรองตามสถานะ
    if (selectedStatusFilter.value && selectedStatusFilter.value !== 'ทั้งหมด') {
        filtered = filtered.filter((member) => member.status === selectedStatusFilter.value);
    }

    return filtered;
});

const fetchMembers = async () => {
    try {
        let url = 'https://6e9fdf451a56.ngrok-free.app/package/backend/get_members.php';

        // ตรวจสอบว่ามีการเลือกปีและเดือนหรือไม่
        if (selectedYearFilter.value && selectedMonthFilter.value) {
            // แยกปีและเดือนจาก selectedMonthFilter
            const [year, month] = selectedMonthFilter.value.split('-');
            const buddhistYear = parseInt(year) + 543;  // คำนวณปีพุทธศักราช
            const monthNumber = parseInt(month); // แปลงเดือนเป็นตัวเลข

            url = 'https://6e9fdf451a56.ngrok-free.app/package/backend/get_members.php?buddhist_year=${buddhistYear}&month_number=${monthNumber}';
        } else if (selectedYearFilter.value) {
            // หากเลือกแค่ปี, ให้ดึงข้อมูลสมาชิกตามปี
            const buddhistYear = parseInt(selectedYearFilter.value) + 543;
            url = 'https://6e9fdf451a56.ngrok-free.app/package/backend/get_members.php?buddhist_year=${buddhistYear}';
        }

        // เพิ่มการกรองประเภทสมาชิก (ถ้าเลือก)
        if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
            url += `&member_type=${selectedMemberTypeFilter.value}`;
        }

        // เพิ่มการกรองสถานะ (ถ้าเลือก)
        if (selectedStatusFilter.value && selectedStatusFilter.value !== 'ทั้งหมด') {
            url += `&status=${selectedStatusFilter.value}`;
        }

        console.log("Fetching URL: ", url); // ตรวจสอบ URL ที่ใช้ในการ fetch

        const res = await fetch(url);
        if (!res.ok) throw new Error('Failed to fetch members');
        const data = await res.json();
        members.value = data;

        console.log('Members:', members.value);
    } catch (error) {
        console.error(error);
    }
};

// เมื่อปีหรือเดือนถูกเลือก, ให้เรียก fetchMembers ใหม่
watch([selectedYearFilter, selectedMonthFilter], () => {
    fetchMembers();
});



onMounted(() => {
    fetchMembers();
});


 
function getProfileImageUrl(path: string | null): string {
    if (!path || path.trim() === '') {
        return defaultAvatar;
    }
    return 'https://6e9fdf451a56.ngrok-free.app/package/backend/${path}';
}

function formatCurrency(value: number | string): string {
    const numberValue = Number(value);
    if (isNaN(numberValue)) return '-';
    return numberValue.toLocaleString('th-TH', { style: 'currency', currency: 'THB' });
}



const years = ref<string[]>([]);

// คำนวณปีพุทธศักราชที่สามารถเลือกได้ (ย้อนหลัง 7 ปี)
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

        const res = await fetch('https://6e9fdf451a56.ngrok-free.app/package/backend/edit_profile.php', {
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



function exportToExcel() {
    const headers = ['ลำดับ', 'ชื่อบริษัท', 'ปี/เดือน'];  // Column headers for the table
    let totalMembers = 0;
    let missingMembers = 0;

    // Group members by "ประเภทสมาชิก" (member type)
    const groupedByType: { [key: string]: any[] } = {};
    members.value.forEach((member, index) => {
        // Create row for each member
        const row = [
            index + 1,  // ลำดับ (Index)
            member.fullname,  // ชื่อบริษัท (Company Name)
            member.status === 'กรอกข้อมูลเรียบร้อย' ? '✓' : '❌'  // ปี/เดือน (Year/Month) status
        ];

        // Group by member type
        if (!groupedByType[member.member_type]) {
            groupedByType[member.member_type] = [];
        }
        groupedByType[member.member_type].push(row);

        totalMembers++;
        if (member.status !== 'กรอกข้อมูลเรียบร้อย') {
            missingMembers++;
        }
    });

    // Create workbook
    const wb = XLSX.utils.book_new();

    // Add a sheet for each category (member type)
    Object.keys(groupedByType).forEach((memberType) => {
        const rows = [];

        // Add category header in the first row
        const categoryHeader = `${memberType} (หมวดหมู่)`;
        rows.push([categoryHeader, '', '']);  // Category header in the first row

        // Add an empty row before the column headers (to separate the category header from the table headers)
        rows.push([]);  // Empty row

        // **Move headers to the first row**
        rows.push(headers);

        // Initialize the worksheet at this point
        const ws = XLSX.utils.aoa_to_sheet(rows);  // Create worksheet after adding rows

        // Add rows for each member under this category
        groupedByType[memberType].forEach((row, rowIndex) => {
            rows.push(row);

            // Apply background color based on the 'ปี/เดือน' status
            const rowIdx = rowIndex + 2;  // Excel rows start from 2 (due to the header being row 2)
            const cellRef = XLSX.utils.encode_cell({ r: rowIdx, c: 2 }); // Check the 'ปี/เดือน' column (column 2)

            if (row[2] === '❌') {
                // Red background for missing data
                if (!ws[cellRef]) ws[cellRef] = {}; // Ensure row exists
                ws[cellRef].s = {
                    fill: {
                        patternType: "solid",
                        fgColor: { rgb: "FF9999" }  // Red background
                    }
                };
            } else if (row[2] === '✓') {
                // White background for filled data
                if (!ws[cellRef]) ws[cellRef] = {}; // Ensure row exists
                ws[cellRef].s = {
                    fill: {
                        patternType: "solid",
                        fgColor: { rgb: "FFFFFF" }  // White background
                    }
                };
            }
        });

       
        rows.push([ 
            'รวม',  
            '',     
            totalMembers  
        ]);

        rows.push([ 
            'ยังไม่ได้กรอก',  // Merged cell in the first column
            '',               // Empty cell for the second column
            missingMembers    // Missing data in the third column
        ]);

        // Re-create the worksheet after rows are added
        const updatedWs = XLSX.utils.aoa_to_sheet(rows);  // Re-create worksheet with updated rows

        // Set column widths for better readability
        updatedWs['!cols'] = [
            { wch: 10 },  // Column for 'ลำดับ'
            { wch: 20 },  // Column for 'ชื่อบริษัท'
            { wch: 12 },  // Column for 'ปี/เดือน'
        ];

        // Merge category header cells
        updatedWs['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 2 } }];  // Merge the category header row (first row)

        // Merge the first two columns of the summary rows
        updatedWs['!merges'].push(
            { s: { r: rows.length - 2, c: 0 }, e: { r: rows.length - 2, c: 1 } },  // Merge cells for 'รวม'
            { s: { r: rows.length - 1, c: 0 }, e: { r: rows.length - 1, c: 1 } }   // Merge cells for 'ยังไม่ได้กรอก'
        );

      
        XLSX.utils.book_append_sheet(wb, updatedWs, memberType);
    });

   
    XLSX.writeFile(wb, 'members_data.xlsx');
}

</script>


<template>
    <v-row>
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
    <v-btn-group color="#b2d7ef" density="comfortable" rounded="pill" divided>
      <v-btn
        class="v-btn v-btn--flat v-theme--BLUE_THEME bg-primary v-btn--density-default v-btn--rounded v-btn--size-default"
      >
        <div class="text-none font-weight-regular primary">เพิ่มข้อมูลสมาชิกสมาคม</div>
        <v-dialog activator="parent" max-width="800">
          <template v-slot:default="{ isActive }">
            <v-card rounded="lg">
              <v-card-title class="d-flex justify-space-between align-center ps-5 py-5">
                <div class="text-h5 text-medium-emphasis ps-2">
                  <h3 class="text-h3 mb-1">ลงทะเบียนสมาชิก</h3>
                  <div class="text-subtitle-1 opacity-80" style="font-weight: 300">
                    เพิ่มข้อมูลสมาชิกสมาคม
                  </div>
                </div>
                <v-btn icon="mdi-close" variant="text" @click="isActive.value = false"></v-btn>
              </v-card-title>
              <v-divider class="mb-4"></v-divider>
              <v-card-text>
                <form @submit.prevent="register">
                  <v-row>
                    <v-col cols="12" sm="6">
                      <v-label class="mb-1">ชื่อบริษัท</v-label>
                      <v-text-field id="fullname" v-model="fullname" variant="outlined" hide-details color="primary"></v-text-field>
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-label class="mb-1">ประเภทสมาชิก</v-label>
                      <v-select :items="memberTypes" v-model="selectedMemberTypeForm" />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-label class="mb-1">อีเมล</v-label>
                      <v-text-field variant="outlined" type="email" hide-details color="primary" id="email" v-model="email"></v-text-field>
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-label class="mb-1">รหัสผ่าน</v-label>
                      <v-text-field
                        id="password"
                        v-model="password"
                        :append-icon="show1 ? 'mdi-eye' : 'mdi-eye-off'"
                        :rules="[rules.required, rules.min]"
                        :type="show1 ? 'text' : 'password'"
                        name="input-10-1"
                        hint="At least 8 characters"
                        counter
                        @click:append="show1 = !show1"
                        hide-details
                      ></v-text-field>
                    </v-col>

                    <v-col cols="12">
                      <v-label class="mb-1">สิทธิการเข้าถึงข้อมูล</v-label>
                      <v-select :items="items" v-model="selectedRole" hide-details></v-select>
                    </v-col>

                    <v-col cols="12">
                      <v-label class="mb-1">อัพโหลดรูปโปรไฟล์</v-label>
                      <v-file-input label="File input" v-model="selectedFile" accept="image/*" @change="handleFileUpload"></v-file-input>
                    </v-col>

                    <v-col cols="12">
                      <v-btn type="submit" color="primary" size="large" block flat class="w-100">
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

                   <br><br>

                    <v-row class="mb-4" dense>
          

                 
<v-col cols="12" sm="3">
    <v-select
        v-model="selectedYearFilter"
        :items="years"
        label="ประจำปี"
        dense
        clearable
        :menu-props="{ maxHeight: '200' }"
    />
</v-col>

<v-col cols="12" sm="3">
    <v-select
        v-model="selectedMonthFilter"
        :items="months"
        label="เดือน"
        dense
        clearable
        item-title="label"
        item-value="value"
        :menu-props="{ maxHeight: '200' }"
        :disabled="!selectedYearFilter" 
    />
</v-col>

              <v-col cols="12" sm="3">
                            <v-select
                                v-model="selectedMemberTypeFilter"
                                :items="['ทั้งหมด', ...filterMemberTypes]"
                                label="ประเภทสมาชิก"
                                dense
                                clearable
                                :menu-props="{ maxHeight: '200' }"
                            />
                        </v-col>

            

                        <v-col cols="12" sm="3">
                            <v-select
                                v-model="selectedStatusFilter"
                                :items="['ทั้งหมด', ...statuses]"
                                label="สถานะ"
                                dense
                                clearable
                                :menu-props="{ maxHeight: '200' }"
                            />
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
                                            <td colspan="4" class="text-center text-subtitle-1 py-4">ไม่พบข้อมูลมูลสมาชิก</td>
                                        </tr>
                                        <tr v-else v-for="(member, index) in filteredMembers" :key="index">
                                            <td class="ps-0 py-3">
                                                <div class="d-flex align-center">
                                                    <v-avatar size="48">
                                                        <img
                                                            :src="getProfileImageUrl(member.profile_image)"
                                                            alt="user"
                                                            style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover"
                                                            @error="
                                                                (event) => {
                                                                    const target = event.target as HTMLImageElement;
                                                                    if (target) {
                                                                        target.src = defaultAvatar;
                                                                    }
                                                                }
                                                            "
                                                        />
                                                    </v-avatar>
                                                    <div class="mx-4">
                                                        <h4 class="text-16 text-no-wrap font-weight-medium">{{ member.fullname }}</h4>
                                                        <span class="text-subtitle-1" style="color: red">{{ member.member_type }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <h5 class="text-no-wrap text-subtitle-1 textSecondary">{{ member.email }}</h5>
                                            </td>
                                            <td>
                                                <v-chip
                                                    :color="member.status === 'กรอกข้อมูลเรียบร้อย' ? 'success' : 'error'"
                                                    label
                                                    small
                                                    class="ma-2 rounded-pill"
                                                >
                                                    {{ member.status }}
                                                </v-chip>
                                            </td>
                                            <!-- <td class="text-end">
      <h4 class="text-no-wrap text-subtitle-1 textSecondary">{{ formatCurrency(member.contractValue) }}</h4>
    </td> -->

                                            <td>
                                           
                                         <v-dialog max-width="500">
    <template v-slot:activator="{ props: activatorProps }">
        <v-btn
            v-bind="activatorProps"
            variant="flat" 
            @click="openEditDialog(member.id)"
            
        > <i class="mdi-pencil mdi v-icon notranslate v-theme--BLUE_THEME v-icon--size-small text-info v-icon--clickable me-2" role="button" aria-hidden="false" tabindex="0"></i></v-btn>   
        
    </template>

    <template v-slot:default="{ isActive }">
        <v-card rounded="lg">
            <v-card-title> แก้ไขข้อมูลสมาชิก </v-card-title>
            <v-card-text>
                <form @submit.prevent="updateMember(isActive)">
                    <v-row>
                        <v-col cols="12" sm="6">
                            <v-label>ชื่อบริษัท</v-label>
                            <v-text-field v-model="fullname" readonly></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-label>ประเภทสมาชิก</v-label>
                            <v-select v-model="selectedMemberTypeForm" :items="memberTypes"></v-select>
                        </v-col>
                        <v-col cols="12">
                            <v-label>อีเมล</v-label>
                            <v-text-field v-model="email" readonly></v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-label>สิทธิการเข้าถึงข้อมูล</v-label>
                            <v-select v-model="selectedRole" :items="items"></v-select>
                        </v-col>
                        <v-col cols="12">
                            <v-btn type="submit" color="primary">บันทึกการแก้ไข</v-btn>
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
            <v-btn color="primary" text @click="closeMessageBox">ปิด</v-btn>
        </v-card-actions>
    </v-card>
</v-dialog>

                                                <!-- <v-btn @click="openEditDialog(member.id)">แก้ไขข้อมูล</v-btn>

                                                <v-dialog v-model="dialogActive" max-width="800">
                                                    <v-card rounded="lg">
                                                        <v-card-title> แก้ไขข้อมูลสมาชิก </v-card-title>
                                                        <v-card-text>
                                                            <form @submit.prevent="updateMember">
                                                                <v-row>
                                                                    <v-col cols="12" sm="6">
                                                                        <v-label>ชื่อบริษัท</v-label>
                                                                        <v-text-field v-model="fullname" readonly></v-text-field>
                                                                    </v-col>
                                                                    <v-col cols="12" sm="6">
                                                                        <v-label>ประเภทสมาชิก</v-label>
                                                                        <v-select
                                                                            v-model="selectedMemberTypeForm"
                                                                            :items="memberTypes"
                                                                        ></v-select>
                                                                    </v-col>
                                                                    <v-col cols="12">
                                                                        <v-label>อีเมล</v-label>
                                                                        <v-text-field v-model="email" readonly></v-text-field>
                                                                    </v-col>
                                                                    <v-col cols="12">
                                                                        <v-label>สิทธิการเข้าถึงข้อมูล</v-label>
                                                                        <v-select v-model="selectedRole" :items="items"></v-select>
                                                                    </v-col>
                                                                    <v-col cols="12">
                                                                        <v-btn type="submit" color="primary">บันทึกการแก้ไข</v-btn>
                                                                    </v-col>
                                                                </v-row>
                                                            </form>
                                                        </v-card-text>
                                                    </v-card>
                                                </v-dialog> -->
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
