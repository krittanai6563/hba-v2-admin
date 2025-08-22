
<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import defaultAvatar from '@/assets/images/users/img-logo_0.png';

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

        const res = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/register.php', {
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


const selectedMonthFilter = ref('');
const selectedYearFilter = ref<string>('');

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

// const fetchMembers = async () => {
//     try {
//         let url = 'https://d2e03fa78899.ngrok-free.app/package/backend/get_members_master.php';

//         // URL parameters logic as you already have
//         // Check for year and month parameters, then build the URL

//         const res = await fetch(url, {
//             method: 'POST',  // Ensure the request is a GET request
//         });

//         // Check if the response is JSON
//         const contentType = res.headers.get("Content-Type");
//         if (!contentType || !contentType.includes("application/json")) {
//             // If content type isn't JSON, throw an error
//             throw new Error(`Expected JSON response, but got ${contentType}`);
//         }

//         // If response is JSON, parse it
//         const data = await res.json();
//         members.value = data;  // Populate the members list with the data

//         console.log('Members:', members.value);
//     } catch (error) {
//         console.error("Fetch error:", error);
//         message.value = "An error occurred while fetching the members. Please try again later.";
//     }
// };

const fetchMembers = async () => {
    try {
        let url = 'https://d2e03fa78899.ngrok-free.app/package/backend/get_members_master.php';

        // Append the filters to the URL
        if (selectedYearFilter.value && selectedMonthFilter.value) {
            const [year, month] = selectedMonthFilter.value.split('-');
            let buddhistYear = parseInt(year);
            const isBuddhistYear = buddhistYear > 2500;

            if (!isBuddhistYear) {
                buddhistYear += 543;
            }

            const monthNumber = parseInt(month);
            url = `${url}?buddhist_year=${buddhistYear}&month_number=${monthNumber}`;
        } else if (selectedYearFilter.value) {
            let buddhistYear = parseInt(selectedYearFilter.value);
            const isBuddhistYear = buddhistYear > 2500;

            if (!isBuddhistYear) {
                buddhistYear += 543;
            }

            url = `${url}?buddhist_year=${buddhistYear}`;
        }

        if (selectedMemberTypeFilter.value && selectedMemberTypeFilter.value !== 'ทั้งหมด') {
            url += `&member_type=${selectedMemberTypeFilter.value}`;
        }

        if (selectedStatusFilter.value && selectedStatusFilter.value !== 'ทั้งหมด') {
            url += `&status=${selectedStatusFilter.value}`;
        }

        console.log("Fetching URL: ", url);

        const res = await fetch(url, {
            method: 'POST',  
            
        });

        // Check if the response is valid
        if (!res.ok) {
            throw new Error('Failed to fetch members');
        }

        // Parse the response data
        const data = await res.json();
        members.value = data;

        console.log('Members:', members.value);
    } catch (error) {
        console.error("Fetch error:", error);
        message.value = "An error occurred while fetching the members. Please try again later.";
    }
};

// Refetch members whenever filters are updated
watch([selectedYearFilter, selectedMonthFilter, selectedMemberTypeFilter, selectedStatusFilter], () => {
    fetchMembers();
});

// Call fetchMembers when component is mounted
onMounted(() => {
    fetchMembers();
});


// watch([selectedYearFilter, selectedMonthFilter, selectedMemberTypeFilter, selectedStatusFilter], () => {
//     fetchMembers();
// });


// onMounted(() => {
//     fetchMembers();
// });





function getProfileImageUrl(path: string | null): string {
    if (!path || path.trim() === '') {
        return defaultAvatar;
    }
    return `https://d2e03fa78899.ngrok-free.app/package/backend/${path}`;
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

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const yearToDisplay = selectedYearFilter.value || currentYear.toString();

for (let m = 1; m <= (yearToDisplay === currentYear.toString() ? currentMonth : 12); m++) {
    const monthString = m < 10 ? `0${m}` : `${m}`;
    months.value.push({
        value: `${yearToDisplay}-${monthString}`,
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

        const res = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/edit_profile.php', {
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

    if (!selectedYearFilter.value || !selectedMonthFilter.value) {
        alert("กรุณาเลือกปีและเดือนก่อน");
        return;
    }


    let buddhistYear = parseInt(selectedYearFilter.value);
    const isBuddhistYear = buddhistYear > 2500;

    if (!isBuddhistYear) {
        buddhistYear += 543;
    }

    const monthNumber = parseInt(selectedMonthFilter.value.split('-')[1]);


    router.push({
        name: 'post_repost_user',
        query: {
            memberId1,
            buddhist_year: buddhistYear,
            month_number: monthNumber,
        }
    });
};




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
                            </div>
                        </div>
                    </div>
                    <br><br>

                    <v-row class="mb-4">
                        <v-col cols="12" sm="3">
                            <!-- ช่องเลือกปี -->
                            <v-select v-model="selectedYearFilter" :items="years" label="ประจำปี" dense clearable
                                :menu-props="{ maxHeight: '200' }" />
                        </v-col>

                        <v-col cols="12" sm="3">
                            <v-select v-model="selectedMonthFilter" :items="months" label="เดือน" dense clearable
                                item-title="label" item-value="value" :disabled="!selectedYearFilter"
                                :menu-props="{ maxHeight: '200' }" />

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
                                            <!-- <th class="text-subtitle-1 font-weight-medium">อีเมล</th> -->
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
                                            <!-- <td>
                                                <h5 class="text-no-wrap text-subtitle-1 textSecondary">{{ member.email
                                                    }}</h5>
                                            </td> -->
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
                                                                        class="v-btn__underlay"></span><!----><span
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
                                                                        </svg></span><!----><!----></button></v-btn>

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
                                                            class="v-btn__underlay"></span><!----><span
                                                            class="v-btn__content" data-no-activator=""><svg
                                                                xmlns="http://www.w3.org/2000/svg" width="18"
                                                                height="18" viewBox="0 0 24 24">
                                                                <path fill="#001dff" fill-rule="evenodd"
                                                                    d="M11.5 2.75a8.75 8.75 0 1 0 0 17.5a8.75 8.75 0 0 0 0-17.5M1.25 11.5c0-5.66 4.59-10.25 10.25-10.25S21.75 5.84 21.75 11.5c0 2.56-.939 4.902-2.491 6.698l3.271 3.272a.75.75 0 1 1-1.06 1.06l-3.272-3.271A10.2 10.2 0 0 1 11.5 21.75c-5.66 0-10.25-4.59-10.25-10.25"
                                                                    clip-rule="evenodd" />
                                                            </svg></span><!----><!----></button>
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

