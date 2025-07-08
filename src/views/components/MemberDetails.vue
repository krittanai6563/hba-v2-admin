<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { toRaw } from 'vue';  // นำเข้า toRaw
import logo from '@/assets/images/logos/logo-dark.svg';
import { useRouter } from 'vue-router';
import * as XLSX from 'xlsx';

const router = useRouter();


const route = useRoute();

const props = defineProps<{
  monthName: string
  quarter: number
}>()

const userId = localStorage.getItem('user_id')
const userRole = localStorage.getItem('user_role') || 'user'

// ดึงข้อมูลจาก URL query
const memberId = ref<number | null>(null);
const buddhistYear = ref<string | null>(null);
const monthNumber = ref<string | null>(null);
const memberDetails = ref<any>(null); // ข้อมูลสมาชิก
const data = ref<Record<string, any>>({}); // กำหนดชนิดให้เป็น Record

onMounted(() => {
  // รับ memberId, buddhist_year, month_number จาก URL query
  memberId.value = parseInt(route.query.memberId1 as string);
  buddhistYear.value = route.query.buddhist_year as string;
  monthNumber.value = route.query.month_number as string;

  // เรียก API เพื่อดึงข้อมูลสมาชิก
  fetchMemberDetails();
});

const fetchMemberDetails = async () => {
  if (!memberId.value) return;

  try {
    const response = await fetch(`https://6e9fdf451a56.ngrok-free.app/package/backend/get_members_master-D.php?id=${memberId.value}&buddhist_year=${buddhistYear.value}&month_number=${monthNumber.value}`);
    const result = await response.json();

    console.log(result);  // ตรวจสอบว่าได้ข้อมูลถูกต้อง

    memberDetails.value = result.user_info;  // ข้อมูลสมาชิก
    data.value = result.data;  // ข้อมูลที่คำนวณแล้ว
    console.log('data.value:', data.value);  // ตรวจสอบว่า data.value ถูกตั้งค่าแล้วหรือไม่

    // ตรวจสอบว่า data.value ถูกต้องก่อน
    if (data.value && Object.keys(data.value).length > 0) {
      const trimmedData: ContractData = {}
      for (const region in data.value) {
        const r = region.trim();
        trimmedData[r] = {}
        for (const type in data.value[region]) {
          // ใช้ toRaw เพื่อดึงค่าจาก Proxy
          trimmedData[r][type.trim()] = toRaw(data.value[region][type])
        }
      }

      contractData.value = trimmedData; // กำหนดค่า contractData.value
      console.log(contractData.value);
    } else {
      console.error("No data available in the response.");
    }
  } catch (err) {
    console.error('❌ Error fetching contract data:', err);
  }
};

interface ContractDetail {
  total_units: number
  total_value: number
  total_area: number
  avg_price_per_sqm?: number
}
type RegionData = {
  [type: string]: ContractDetail
}
type ContractData = {
  [region: string]: RegionData
}

const contractTypes = [
  'ไม่เกิน 2.50 ล้านบาท',
  '2.51 - 5 ล้านบาท',
  '5.01 - 10 ล้านบาท',
  '10.01 - 20 ล้านบาท',
  '20.01 ล้านขึ้นไป'
]

const regions = [
  'กรุงเทพปริมณฑล',
  'ภาคเหนือ',
  'ภาคตะวันออกเฉียงเหนือ',
  'ภาคกลาง',
  'ภาคตะวันออก',
  'ภาคใต้',
  'ภาคตะวันตก'
]

const contractData = ref<ContractData>({})

function convertMonthToNumber(monthLabel: string): number {
  const months = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
  ]
  return months.findIndex(m => monthLabel.includes(m)) + 1
}
function extractBuddhistYear(monthLabel: string): number {
  const match = monthLabel.match(/\d{4}/)
  const year = match ? parseInt(match[0]) : null
  return year ? year : new Date().getFullYear() + 543
}

// เพิ่มฟังก์ชัน getMonthName
const getMonthName = (monthNumber: string | null): string => {
  const monthNames = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
  ];
  const monthIndex = parseInt(monthNumber as string) - 1; // เนื่องจาก array เริ่มต้นที่ 0
  return monthNames[monthIndex] || '';
};

const formatCurrency = (value: number | string): string => {
  const numberValue = Number(value);
  if (isNaN(numberValue)) return '-';
  return numberValue.toLocaleString('th-TH', { style: 'currency', currency: 'THB' }).replace('฿', '') + ' บาท';
};


function getCell(region: string, type: string, field: keyof ContractDetail): string {
  if (!region || !type || !field) {
    return '-';
  }

  const r = region.trim().toLowerCase();
  const t = type.trim().toLowerCase();

  const data = contractData.value ?? {};
  const regionData = data[r];

  if (regionData && regionData[t]) {
    const val = regionData[t]?.[field];
    if (val != null) {

      if (field === 'avg_price_per_sqm' && typeof val === 'number') {
        return val.toFixed(2); 
      }
      return typeof val === 'number' ? val.toLocaleString() : String(val);
    }
  }

  return '-'; 
}



function getTotal(field: keyof ContractDetail): number {
  let total = 0;

  for (const region in contractData.value) {
    for (const type in contractData.value[region]) {
      total += contractData.value[region][type]?.[field] ?? 0;
    }
  }

  return total;
}

const goBack = () => {
  router.back(); 
};


const exportToExcel = () => {
  const wsData: any[] = [];

  // 1. แสดงรายละเอียดของสมาชิก
  wsData.push(['รายละเอียดของสมาชิก', '', '', '', '', '', '', '']);
  wsData.push([`ชื่อ: ${memberDetails.value.fullname}`, '', '', '', '', '', '', '']);
  wsData.push([`ประจำเดือน: ${getMonthName(monthNumber.value)} ปี ${buddhistYear.value}`, '', '', '', '', '', '', '']);
  wsData.push([`Email: ${memberDetails.value.email}`, '', '', '', '', '', '', '']);
  wsData.push([`ประเภทสมาชิก: ${memberDetails.value.member_type}`, '', '', '', '', '', '', '']);
  wsData.push([]);  // เว้นบรรทัดหลังรายละเอียดสมาชิก

  // 2. แสดงข้อมูลตามประเภทสัญญา
  contractTypes.forEach((label) => {
    // หัวข้อประเภทสัญญา
    wsData.push([label, '', '', '', '', '', '', '']);

    // สำหรับแต่ละภูมิภาค
    const units = regions.map((region: string) => getCell(region, label, 'total_units'));
    const values = regions.map((region: string) => getCell(region, label, 'total_value'));
    const areas = regions.map((region: string) => getCell(region, label, 'total_area'));
    const avgPrices = regions.map((region: string) => getCell(region, label, 'avg_price_per_sqm'));

    // เพิ่มข้อมูลในตาราง
    wsData.push(['จำนวนหลัง', ...units]);
    wsData.push(['มูลค่ารวม', ...values]);
    wsData.push(['พื้นที่ใช้สอย', ...areas]);
    wsData.push(['ราคาเฉลี่ย ตร.ม', ...avgPrices]);

    // เว้นบรรทัดหลังหัวข้อประเภทสัญญา
    wsData.push([]);
  });

  // 3. สรุปข้อมูลยอดเซ็นสัญญา
  const totalUnits = getTotal('total_units');
  const totalValue = getTotal('total_value');
  const totalArea = getTotal('total_area');
  const avgPrice = totalValue / totalArea;

  wsData.push(['สรุปข้อมูลยอดเซ็นสัญญา', '', '', '', '', '', '', '']);
  wsData.push(['จำนวนหลัง', formatCurrency(totalUnits)]);
  wsData.push(['มูลค่ารวม', formatCurrency(totalValue)]);
  wsData.push(['พื้นที่ใช้สอย', formatCurrency(totalArea)]);
  wsData.push(['ราคาเฉลี่ย ตร.ม', avgPrice.toFixed(2)]);

  // 4. สร้าง worksheet จาก wsData
  const ws = XLSX.utils.aoa_to_sheet(wsData);

const columnWidths = wsData[0].map((_: any, index: number) => ({
  wch: Math.max(...wsData.map((row: any[]) => (row[index] ? row[index].toString().length : 0)))
}));



  ws['!cols'] = columnWidths;

  // 5. สร้าง workbook และเพิ่ม worksheet ลงไป
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Data');

  // 6. ดาวน์โหลดไฟล์ Excel
  XLSX.writeFile(wb, 'report.xlsx');
};


</script>

<template>
  <v-col v-if="memberDetails">
    <v-card>
      <v-card-text>
        <div class="d-flex justify-space-between align-content-start mb-6">
          <div class="logo"><img :src="logo" alt="home"></div>
          <div><span
              class="v-btn v-theme--BLUE_THEME bg-success v-btn--density-default v-btn--size-default v-btn--variant-flat " style="border-radius: 50px;"
              draggable="false"><!----><span class="v-chip__underlay"></span><!----><!---->
              <div class="v-chip__content" data-no-activator="" > {{ memberDetails.status }}</div>
              <!----><!---->
            </span>
          
          </div>
        </div>

        <div class="mb-6">
          <div class="v-row">
            <div class="v-col-lg-4 v-col-12">
              <h2 class="text-1 mb-3 font-weight-medium" style="color:#f6c000;">รายละเอียดของ {{ memberDetails.fullname }} </h2>
          
              <div class="text-14 textSecondary lh-normal">
                <p class="mb-1 "><span style="color: #000;" class="font-weight-medium">ประจำเดือน :</span> {{ getMonthName(monthNumber) }} ปี {{ buddhistYear }}</p>
                <p class="mb-1"> <span style="color: #000;" class="font-weight-medium">Email:</span> {{ memberDetails.email }}</p>
                <p class="mb-1"><span style="color: #000;" class="font-weight-medium">ประเภทสมาชิก:</span> {{ memberDetails.member_type }}</p>


              </div>
            </div>


          </div>
        </div>


        <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
          <div class="v-table__wrapper">
            <table>
              <thead>

                <tr>
                  <th></th>
                  <th v-for="region in regions" :key="region" class="text-p" style="font-size: 13px;">
                    {{ region }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(label, i) in contractTypes" :key="i">

                  <tr class="month-item" >
                    <td>
                      <h6 class="text-p" style="font-size: 12px; font-weight: 600; color: #725AF2;">{{ label }}</h6>
                    </td>
                    <td v-for="region in regions" :key="region">
                      <h6 class="text-subtitle-1"></h6>
                    </td>
                  </tr>


                  <tr class="month-item">
                    <td>
                      <h6 class="text-p" style="font-size: 12px; font-weight: 400;">จำนวนหลัง</h6>
                    </td>
                    <td v-for="region in regions" :key="region">
                      <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label,
                        'total_units') }}
                      </h6>
                    </td>
                  </tr>


                  <tr class="month-item">
                    <td>
                      <h6 class="text-p" style="font-size: 12px; font-weight: 400;">มูลค่ารวม</h6>
                    </td>
                    <td v-for="region in regions" :key="region">
                      <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label,
                        'total_value')
                      }}</h6>
                    </td>
                  </tr>


                  <tr class="month-item">
                    <td>
                      <h6 class="text-p" style="font-size: 12px; font-weight: 400;">พื้นที่ใช้สอย</h6>
                    </td>
                    <td v-for="region in regions" :key="region">
                      <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label,
                        'total_area') }}
                      </h6>
                    </td>
                  </tr>


                  <tr class="month-item">
                    <td>
                      <h6 class="text-p" style="font-size: 12px; font-weight: 400;">ราคาเฉลี่ย ตร.ม</h6>
                    </td>
                    <td v-for="region in regions" :key="region">
                      <h6 class="text-p" style="font-size: 13px; font-weight: 400;">
                        <h6 class="text-p" style="font-size: 13px; font-weight: 400;">
                          {{ getCell(region, label, 'avg_price_per_sqm') }}
                        </h6>
                      </h6>
                    </td>
                  </tr>



                </template>
              </tbody>
            </table>

          </div>
        </div>
        <div class="v-row d-flex justify-end border-t mt-1">
          <div class="v-col-md-4 v-col-12 mt-4">
            <div class="d-flex align-center justify-space-between text-14 font-weight-semibold mb-4">
              <p class="text-muted">จำนวนหลัง</p>
              <h4 class="text-1 ">{{ formatCurrency(getTotal('total_units')) }}</h4>
            </div>
            <div class="d-flex align-center justify-space-between text-14 font-weight-semibold mb-4">
              <p class="text-muted">มูลค่ารวม</p>
              <h4 class="text-1">{{ formatCurrency(getTotal('total_value')) }}</h4>
            </div>
            <div class="d-flex align-center justify-space-between text-14 font-weight-semibold mb-4">
              <p class="text-muted">พื้นที่ใช้สอย</p>
              <h4 class="text-1">{{ formatCurrency(getTotal('total_area')) }}</h4>
            </div>
            <div class="d-flex align-center justify-space-between text-14 font-weight-semibold">
              <p class="text-muted">ราคาเฉลี่ย</p>
              <h4 class="text-1">{{ (getTotal('total_value') / getTotal('total_area')).toFixed(2) }} ตร.ม</h4>
            </div>
          </div>
        </div>

        <div class="d-flex ga-3 justify-end mt-6"><a
  class="v-btn v-btn--flat v-theme--BLUE_THEME bg-warning v-btn--density-default v-btn--size-default v-btn--variant-elevated"
  @click="goBack">
  <span class="v-btn__overlay"></span><span class="v-btn__underlay"></span>
  <span class="v-btn__content" data-no-activator="">กลับไปหน้าหลัก</span>
</a>
<a
            class="v-btn v-btn--flat v-theme--BLUE_THEME bg-primary v-btn--density-default v-btn--size-default v-btn--variant-elevated"
              @click="exportToExcel"><span class="v-btn__overlay"></span><span class="v-btn__underlay"></span><!----><span
              class="v-btn__content" data-no-activator="">expost to Excel</span><!----><!----></a></div>

      </v-card-text>
    </v-card>

  </v-col>





</template>

<style scoped>
.text-h6 {
  font-size: 18px;
}

.month-item td {
  padding: 8px;
}
</style>
