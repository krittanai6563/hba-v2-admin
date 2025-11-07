<script setup lang="ts">
import { ref, onMounted, defineProps } from 'vue'

const props = defineProps<{
  monthName: string
  quarter: number
}>()

const userId = localStorage.getItem('user_id')
const userRole = localStorage.getItem('user_role') || 'user'

interface ContractDetail {
  unit: number
  value: number
  area: number
  price_per_sqm?: number
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

function formatCellNumber(value: any, field: keyof ContractDetail | 'price_per_sqm'): string {
  const num = Number(value);
  
  // --- นี่คือส่วนที่แก้ไข ---
  // ถ้าค่าเป็น null, undefined, หรือไม่ใช่ตัวเลข, หรือเป็น 0
  if (value == null || isNaN(num) || num === 0) {
    if (field === 'unit') {
      return '0'; // ถ้าเป็น 'unit' ให้แสดง '0'
    } else {
      return '0.00'; // ถ้าเป็น field อื่น (value, area, price) ให้แสดง '0.00'
    }
  }
  // --- สิ้นสุดส่วนที่แก้ไข ---

  if (field === 'unit') {
    // 'unit' (จำนวนหลัง) - ไม่ต้องมีทศนิยม
    return num.toLocaleString('th-TH');
  } else {
    // 'value', 'area', 'price_per_sqm' - มีทศนิยม 2 ตำแหน่ง
    return num.toLocaleString('th-TH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }
}


function convertMonthToNumber(monthLabel: string): number {
  const months = [
    'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
    'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'
  ]
  return months.findIndex(m => monthLabel.includes(m)) + 1
}
function extractBuddhistYear(monthLabel: string): number {
  const match = monthLabel.match(/\d{4}/)
  const year = match ? parseInt(match[0]) : null
  return year ? year : new Date().getFullYear() + 543
}

// (!!!) 2. แก้ไข getCell ให้เรียกใช้ formatCellNumber
function getCell(region: string, type: string, field: keyof ContractDetail): string {
  const r = region.trim().toLowerCase()
  const t = type.trim().toLowerCase()

  const data = contractData.value ?? {}
  for (const regKey in data) {
    if (regKey.trim().toLowerCase() === r) {
      const regionObj = data[regKey]
      for (const typeKey in regionObj) {
        if (typeKey.trim().toLowerCase() === t) {
          const val = regionObj[typeKey]?.[field]
          // เรียกใช้ helper function
          return formatCellNumber(val, field)
        }
      }
    }
  }
  // คืนค่า '-' ผ่าน helper
  return formatCellNumber(null, field)
}

function getRegionTotal(region: string, field: keyof ContractDetail): string {
  const r = region.trim().toLowerCase()
  const data = contractData.value ?? {}

  for (const regKey in data) {
    if (regKey.trim().toLowerCase() === r) {
      const total = contractTypes.reduce((total, type) => {
        const t = type.trim().toLowerCase()
        for (const typeKey in data[regKey]) {
          if (typeKey.trim().toLowerCase() === t) {
            total += data[regKey][typeKey]?.[field] ?? 0
            break
          }
        }
        return total
      }, 0)
      
      // เรียกใช้ helper function กับผลรวม
      return formatCellNumber(total, field)
    }
  }
  // คืนค่า 0 ผ่าน helper
  return formatCellNumber(0, field)
}

const fetchContractData = async () => {
  const buddhistYear = extractBuddhistYear(props.monthName)
  const monthNumber = convertMonthToNumber(props.monthName)

  const payload: Record<string, any> = {
    role: userRole,
    buddhist_year: buddhistYear,
    month_number: monthNumber,
    quarter: props.quarter
  }

if (userId) {
  payload.user_id = userId
}

  console.log('📤 Sending payload:', payload)

  try {
    const response = await fetch('https://uat.hba-sales.org/backend/get_contract_data.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })

    const rawData = await response.json()
    console.log('📦 Contract Data from API:', rawData)

    if (rawData.error) {
      console.error('⛔️', rawData.error)
      return
    }

    const trimmedData: ContractData = {}
    for (const region in rawData) {
      const r = region.trim()
      trimmedData[r] = {}
      for (const type in rawData[region]) {
        trimmedData[r][type.trim()] = rawData[region][type]
      }
    }

    contractData.value = trimmedData
  } catch (err) {
    console.error('❌ Error fetching contract data:', err)
  }
}

onMounted(fetchContractData)

// (!!!) 4. แก้ไข getRegionAvgPrice (ใช้ชื่อ price_per_sqm เพื่อความสอดคล้อง)
function getRegionAvgPrice(region: string): string {
  const regionData = contractData.value[region] ?? {}
  let totalValue = 0
  let totalArea = 0

  for (const priceRange in regionData) {
    const data = regionData[priceRange]
    totalValue += data?.value ?? 0
    totalArea += data?.area ?? 0
  }

  const avg = totalArea > 0 ? totalValue / totalArea : 0
  
  // เรียกใช้ helper function
  return formatCellNumber(avg, 'price_per_sqm')
}

function getHorizontalTotalValue(priceRangeLabel: string, field: keyof ContractDetail): number {
  let horizontalTotal = 0;
  const t = priceRangeLabel.trim().toLowerCase();
  const data = contractData.value ?? {};

  for (const regionKey in data) { // วนลูปผ่านทุกภูมิภาค (คอลัมน์)
    if (data.hasOwnProperty(regionKey)) {
      const regionObj = data[regionKey];
      for (const typeKey in regionObj) {
        if (typeKey.trim().toLowerCase() === t) {
          // รวมค่าตัวเลขดิบของ field ที่ต้องการ
          horizontalTotal += regionObj[typeKey]?.[field] ?? 0;
          break; 
        }
      }
    }
  }
  return horizontalTotal;
}

// (!!!) 5. แก้ไข getFormattedHorizontalTotal ให้เรียกใช้ formatCellNumber
function getFormattedHorizontalTotal(priceRangeLabel: string, field: keyof ContractDetail, isPricePerSqm: boolean = false): string {
    
    if (isPricePerSqm) {
        const totalValue = getHorizontalTotalValue(priceRangeLabel, 'value');
        const totalArea = getHorizontalTotalValue(priceRangeLabel, 'area');

        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        
        // เรียกใช้ helper function
        return formatCellNumber(avg, 'price_per_sqm');
    }

    // สำหรับ Unit, Value, Area
    const total = getHorizontalTotalValue(priceRangeLabel, field);
    // เรียกใช้ helper function
    return formatCellNumber(total, field);
}

// (!!!) 6. แก้ไข calculateNumericGrandTotal ให้ถูกต้องมากขึ้น
function calculateNumericGrandTotal(field: 'unit' | 'value' | 'area'): number {
    let grandTotal = 0;
    const data = contractData.value ?? {};
    
    for (const regionKey in data) { // วนลูปทุกภาค
        if (data.hasOwnProperty(regionKey)) {
            grandTotal += contractTypes.reduce((total, type) => { // วนลูปทุกแถว
                total += data[regionKey][type]?.[field] ?? 0
                return total
            }, 0)
        }
    }
    return grandTotal;
}

// (!!!) 7. แก้ไข getGrandOverallTotal ให้เรียกใช้ formatCellNumber
function getGrandOverallTotal(field: keyof ContractDetail | 'price_per_sqm'): string {
    if (field === 'price_per_sqm') {
        const totalValue = calculateNumericGrandTotal('value');
        const totalArea = calculateNumericGrandTotal('area');

        const avg = totalArea > 0 ? totalValue / totalArea : 0;
        // เรียกใช้ helper function
        return formatCellNumber(avg, 'price_per_sqm');
    }
    
    const grandTotal = calculateNumericGrandTotal(field);

    // เรียกใช้ helper function
    return formatCellNumber(grandTotal, field);
}
</script>

<template>
  <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
    <div class="v-table__wrapper">
      <table>
        <thead style="background-color: #F5F5F5;">
          <tr>
            <th class="text-h6"></th>
            <th class="text-h6" :colspan="regions.length + 1" style="text-align: center; border-bottom: 2px solid #00A6D4;">
              ยอดเซ็นสัญญาประจำเดือน {{ props.monthName }} ไตรมาสที่ {{ props.quarter }}
            </th>
          </tr>
          <tr>
            <th></th>
            <th v-for="region in regions" :key="region" class="text-p" style="font-size: 13px;">
              {{ region }}
            </th>
            <th class="text-p" style="font-size: 13px; font-weight: 600; background-color: #FFF3E0;">
              รวม
            </th>
            </tr>
        </thead>
        <tbody>
          <template v-for="(label, i) in contractTypes" :key="i">
           
            <tr class="month-item" style="background-color: #fcf8ff;">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 600; color: #725AF2;">{{ label }}</h6></td>
              <td v-for="region in regions" :key="region"><h6 class="text-subtitle-1"></h6></td>
              <td style="background-color: #FFF3E0;"></td> </tr>

          
            <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">จำนวนหลัง</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;" >{{ getCell(region, label, 'unit') }}</h6>
              </td>
              <td style="background-color: #FFF3E0;">
                <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(label, 'unit') }}</h6>
              </td>
            </tr>

           
            <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">มูลค่ารวม</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label, 'value') }}</h6>
              </td>
              <td style="background-color: #FFF3E0;">
                <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(label, 'value') }}</h6>
              </td>
            </tr>

        
            <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">พื้นที่ใช้สอย</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label, 'area') }}</h6>
              </td>
              <td style="background-color: #FFF3E0;">
                <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(label, 'area') }}</h6>
              </td>
            </tr>


             <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">ราคาเฉลี่ย ตร.ม</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label, 'price_per_sqm') }}</h6>
              </td>
              <td style="background-color: #FFF3E0;">
                <h6 class="text-p" style="font-size: 13px; font-weight: 600;">{{ getFormattedHorizontalTotal(label, 'value', true) }}</h6>
              </td>
            </tr>
          </template>

          <tr class="month-item" style="background-color: #fcf8ff;">
            <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">จำนวนหลัง (รวม)</h6></td>
            <td v-for="region in regions" :key="region">
              <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getRegionTotal(region, 'unit') }}</h6>
            </td>
            <td style="background-color: #FFF3E0;">
              <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getGrandOverallTotal('unit') }}</h6>
            </td>
          </tr>

          <tr class="month-item" style="background-color: #fcf8ff;">
            <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">มูลค่ารวม (รวม)</h6></td>
            <td v-for="region in regions" :key="region">
              <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getRegionTotal(region, 'value') }}</h6>
            </td>
            <td style="background-color: #FFF3E0;">
              <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getGrandOverallTotal('value') }}</h6>
            </td>
          </tr>

          <tr class="month-item" style="background-color: #fcf8ff;">
            <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">พื้นที่ใช้สอย (รวม)</h6></td>
            <td v-for="region in regions" :key="region">
              <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getRegionTotal(region, 'area') }}</h6>
            </td>
            <td style="background-color: #FFF3E0 ;">
              <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getGrandOverallTotal('area') }}</h6>
            </td>
          </tr>
          
          <tr class="month-item" style="background-color: #fcf8ff;">
            <td><h6 class="text-p" style="font-size: 13px; font-weight: 600; color: #F8285A;">ราคาเฉลี่ย ตร.ม. (รวม)</h6></td>
            <td v-for="region in regions" :key="region">
              <h6 class="text-p" style="font-size: 14px; font-weight: 600; color: #F8285A;">{{ getRegionAvgPrice(region) }}</h6>
            </td>
            <td style="background-color: #FFF3E0;">
              <h6 class="text-p" style="font-size: 14px; font-weight: 800; color: #F8285A;">{{ getGrandOverallTotal('price_per_sqm') }}</h6>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.text-h6 {
  font-size: 18px;
}
.month-item td {
  padding: 8px;
}
</style>