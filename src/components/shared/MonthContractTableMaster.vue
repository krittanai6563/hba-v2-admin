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
          return val == null ? '-' : typeof val === 'number' ? val.toLocaleString() : String(val)
        }
      }
    }
  }
  return '-'
}


function getRegionTotal(region: string, field: keyof ContractDetail): string {
  const r = region.trim().toLowerCase()
  const data = contractData.value ?? {}

  for (const regKey in data) {
    if (regKey.trim().toLowerCase() === r) {
      return contractTypes.reduce((total, type) => {
        const t = type.trim().toLowerCase()
        for (const typeKey in data[regKey]) {
          if (typeKey.trim().toLowerCase() === t) {
            total += data[regKey][typeKey]?.[field] ?? 0
            break
          }
        }
        return total
      }, 0).toLocaleString()
    }
  }
  return '0'
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
    const response = await fetch('http://localhost/package/backend/get_contract_data.php', {
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
  return avg.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <div class="v-table v-theme--BLUE_THEME v-table--density-default month-table">
    <div class="v-table__wrapper">
      <table>
        <thead style="background-color: #F5F5F5;">
          <tr>
            <th class="text-h6"></th>
            <th class="text-h6" :colspan="regions.length" style="text-align: center; border-bottom: 2px solid #00A6D4;">
              ยอดเซ็นสัญญาประจำเดือน {{ props.monthName }} ไตรมาสที่ 111222{{ props.quarter }}
            </th>
          </tr>
          <tr>
            <th></th>
            <th v-for="region in regions" :key="region" class="text-p" style="font-size: 13px;">
              {{ region }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(label, i) in contractTypes" :key="i">
           
            <tr class="month-item" style="background-color: #fcf8ff;">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 600; color: #725AF2;">{{ label }}</h6></td>
              <td v-for="region in regions" :key="region"><h6 class="text-subtitle-1"></h6></td>
            </tr>

          
            <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">จำนวนหลัง</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;" >{{ getCell(region, label, 'unit') }}</h6>
              </td>
            </tr>

           
            <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">มูลค่ารวม</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label, 'value') }}</h6>
              </td>
            </tr>

        
            <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">พื้นที่ใช้สอย</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;">{{ getCell(region, label, 'area') }}</h6>
              </td>
            </tr>


             <tr class="month-item">
              <td><h6 class="text-p" style="font-size: 12px; font-weight: 400;">ราคาเฉลี่ย ตร.ม</h6></td>
              <td v-for="region in regions" :key="region">
                <h6 class="text-p" style="font-size: 13px; font-weight: 400;"> <h6 class="text-p" style="font-size: 13px; font-weight: 400;">
  {{ getCell(region, label, 'price_per_sqm') }}
</h6>
</h6>
              </td>
            </tr>
          </template>
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
