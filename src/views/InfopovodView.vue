<template>
  <div class="infopovod-page">
    <AppHeader title="Заполнение инфоповодов" :showBack="false" />

    <div class="page-content">
      <div v-if="loading" class="loading-state glass">
        <span class="material-symbols-rounded spin">progress_activity</span>
        <p>Загрузка инфоповодов...</p>
      </div>

      <div v-else-if="errorMsg" class="error-banner">
        <span class="material-symbols-rounded">error</span>
        <span>{{ errorMsg }}</span>
      </div>

      <div v-else-if="!items.length" class="empty-state glass">
        <span class="material-symbols-rounded">info</span>
        <h3>Инфоповоды не найдены</h3>
        <p>Для данной сделки нет доступных инфоповодов.</p>
        <button class="primary-btn mt-4" @click="finish">Завершить</button>
      </div>

      <div v-else class="infopovod-container">
        <!-- Form Block -->
        <form @submit.prevent="submitForm" class="povod-form">
          <div v-for="item in items" :key="item.id" class="card povod-card glass">
            <h3 class="povod-name">{{ item.name }}</h3>

            <div class="field">
              <label>Статус</label>
              <select class="form-select" v-model="formData[item.id].status_id" required>
                <option value="0" disabled>Выберите статус</option>
                <optgroup label="Успешные">
                  <option v-for="st in item.statuses.success" :key="st.id" :value="st.id">
                    {{ st.name }} {{ st.is_comment_required ? '(Обязателен комментарий)' : '' }}
                  </option>
                </optgroup>
                <optgroup label="Отказ/Ошибка">
                  <option v-for="st in item.statuses.error" :key="st.id" :value="st.id">
                    {{ st.name }} {{ st.is_comment_required ? '(Обязателен комментарий)' : '' }}
                  </option>
                </optgroup>
                <optgroup label="Потенциал">
                  <option v-for="st in item.statuses.potential" :key="st.id" :value="st.id">
                    {{ st.name }} {{ st.is_comment_required ? '(Обязателен комментарий)' : '' }}
                  </option>
                </optgroup>
              </select>
            </div>

            <div class="field mt-3">
              <label>Комментарий (Важна любая обратная связь)</label>
              <textarea
                class="form-textarea"
                rows="3"
                v-model="formData[item.id].comment"
                :required="isCommentRequired(item.id)"
              ></textarea>
            </div>

            <!-- Dynamic fields depending on status -->
            <div class="field mt-3">
              <label>Доп. поля (при необходимости)</label>
              <div class="dynamic-inputs">
                <input type="date" class="form-input" v-model="formData[item.id].next_comm_date" placeholder="След. коммуникация" />
                <input type="tel" class="form-input mt-2" v-model="formData[item.id].phone_sms" placeholder="Телефон для SMS" />
                <select v-if="item.products && item.products.length" class="form-select mt-2" v-model="formData[item.id].product_xml_id">
                  <option value="">Выберите продукт для апробации (опц.)</option>
                  <option v-for="p in item.products" :key="p.xml_id" :value="p.xml_id">{{ p.name }}</option>
                </select>
              </div>
            </div>
          </div>

          <button type="submit" class="primary-btn submit-btn" :disabled="submitting">
            <span v-if="submitting" class="material-symbols-rounded spin">progress_activity</span>
            <span v-else class="material-symbols-rounded">check_circle</span>
            {{ submitting ? 'Сохранение...' : 'Отправить результаты' }}
          </button>
        </form>

        <!-- Voice Input Block (Moved & Disabled) -->
        <div class="card voice-card glass in-development">
          <div class="dev-badge">В разработке</div>
          <h3 class="card-title text-gray-400">Голосовой ввод</h3>
          <p class="card-desc text-gray-500">Продиктуйте итоги по всем инфоповодам, и AI автоматически заполнит поля.</p>

          <button class="voice-btn disabled-btn" disabled>
            <span class="material-symbols-rounded">mic_off</span>
            <span class="font-bold">Начать запись</span>
          </button>
        </div>
      </div>

    </div>

    <!-- Toast внутри корневого div -->
    <div class="toast-container" v-if="toastMsg">
      <div class="toast animate-fade-in-up">
        <span class="material-symbols-rounded">info</span>
        {{ toastMsg }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useVisits } from '../composables/useVisits'
import AppHeader from '../components/AppHeader.vue'

const route = useRoute()
const router = useRouter()
const { fetchInfopovods, submitInfopovods } = useVisits()

const visitId = route.params.id
const loading = ref(true)
const items = ref([])
const errorMsg = ref('')
const toastMsg = ref('')

const formData = reactive({})

// Voice Recording state
const isRecording = ref(false)
const isProcessingAudio = ref(false)
const recordSeconds = ref(0)
let mediaRecorder = null
let audioChunks = []
let timerInterval = null

function formatTime(sec) {
  const m = Math.floor(sec / 60).toString().padStart(2, '0')
  const s = (sec % 60).toString().padStart(2, '0')
  return `${m}:${s}`
}

function showToast(msg) {
  toastMsg.value = msg
  setTimeout(() => { toastMsg.value = '' }, 3000)
}

onMounted(async () => {
  try {
    const res = await fetchInfopovods(visitId)
    items.value = res.items || []
    
    // Initialize form data
    items.value.forEach(item => {
      formData[item.id] = {
        status_id: '0',
        comment: '',
        next_comm_date: '',
        phone_sms: '',
        product_xml_id: ''
      }
    })
  } catch (err) {
    console.error('[Infopovod] load error:', err)
    errorMsg.value = 'Ошибка загрузки инфоповодов'
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop()
  }
})

function isCommentRequired(itemId) {
  const stId = formData[itemId]?.status_id
  if (!stId || stId === '0') return false
  
  const item = items.value.find(i => i.id == itemId)
  if (!item) return false
  
  const allSt = [...item.statuses.success, ...item.statuses.error, ...item.statuses.potential]
  const st = allSt.find(s => s.id == stId)
  return st?.is_comment_required || false
}

// -- Voice Recording --

async function startRecording() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    mediaRecorder = new MediaRecorder(stream)
    audioChunks = []
    
    mediaRecorder.addEventListener('dataavailable', e => audioChunks.push(e.data))
    mediaRecorder.addEventListener('stop', () => {
      stream.getTracks().forEach(track => track.stop())
      if (audioChunks.length > 0) {
        processAudio(new Blob(audioChunks, { type: 'audio/webm' }))
      }
    })
    
    mediaRecorder.start()
    isRecording.value = true
    recordSeconds.value = 0
    timerInterval = setInterval(() => recordSeconds.value++, 1000)
  } catch (err) {
    showToast('Нет доступа к микрофону: ' + err.message)
  }
}

function stopRecording() {
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop()
  }
  isRecording.value = false
  clearInterval(timerInterval)
}

function cancelRecording() {
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop()
  }
  audioChunks = []
  isRecording.value = false
  clearInterval(timerInterval)
}

function getFormStructure() {
  return {
    infopovody: items.value.map(item => ({
      id: item.id.toString(),
      name: item.name,
      statuses: [...item.statuses.success, ...item.statuses.error, ...item.statuses.potential].map(s => ({
        id: s.id.toString(),
        name: s.name
      })),
      products: item.products ? item.products.map(p => ({
        id: p.xml_id,
        name: p.name
      })) : []
    }))
  }
}

async function processAudio(blob) {
  isProcessingAudio.value = true
  try {
    const fd = new FormData()
    fd.append('audio', blob, 'recording.webm')
    fd.append('form_structure', JSON.stringify(getFormStructure()))
    
    const res = await fetch('https://proxytestitnebo.fly.dev/process-audio', {
      method: 'POST',
      body: fd
    })
    
    const data = await res.json()
    if (res.ok && data && data.success) {
      applyAiResult(data.result)
      showToast('Поля успешно заполнены голосом!')
    } else {
      throw new Error(data.error || data.message || 'Ошибка обработки AI')
    }
  } catch (e) {
    console.error('[Infopovod] audio process error', e)
    showToast(e.message)
  } finally {
    isProcessingAudio.value = false
  }
}

function applyAiResult(aiData) {
  if (!aiData || typeof aiData !== 'object') return
  
  for (const [id, povodData] of Object.entries(aiData)) {
    if (!formData[id]) continue
    
    let stVal = null
    let commentVal = null
    
    for (const key in povodData) {
      if (key.includes('status') && povodData[key].value) {
        stVal = povodData[key].value
      }
      if (key.includes('comment') && povodData[key].value) {
        commentVal = povodData[key].value
      }
    }
    
    if (stVal) formData[id].status_id = stVal
    if (commentVal) formData[id].comment = commentVal
  }
}

// -- Form Submit --

const submitting = ref(false)

async function submitForm() {
  // Validate
  for (const id in formData) {
    if (formData[id].status_id === '0') {
      showToast('Пожалуйста, выберите статус для всех инфоповодов.')
      return
    }
  }

  submitting.value = true
  try {
    const payloadItems = []
    for (const id in formData) {
      payloadItems.push({
        id: id,
        ...formData[id]
      })
    }
    
    await submitInfopovods(visitId, payloadItems)
    
    router.replace('/')
  } catch (e) {
    console.error(e)
    showToast('Ошибка при сохранении: ' + e.message)
  } finally {
    submitting.value = false
  }
}

function finish() {
  router.replace('/')
}
</script>

<style scoped>
.infopovod-page {
  display: flex; flex-direction: column; min-height: 100dvh;
}
.page-content {
  flex: 1; padding: var(--space-base); padding-bottom: calc(env(safe-area-inset-bottom, 20px) + 80px);
}

.loading-state, .empty-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 40px var(--space-lg); text-align: center; gap: var(--space-md);
  color: var(--color-text-secondary);
}
.spin { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.error-banner {
  display: flex; align-items: center; gap: 8px; padding: 16px;
  background: rgba(255, 68, 68, 0.1); color: var(--color-danger); border-radius: 12px;
}

.infopovod-container { display: flex; flex-direction: column; gap: var(--space-lg); }

.card { padding: var(--space-lg); border-radius: var(--radius-xl); background: var(--color-bg-card); box-shadow: var(--shadow-sm); }
.card-title { font-size: var(--font-size-md); font-weight: var(--font-weight-bold); margin-bottom: 4px; }
.card-desc { font-size: var(--font-size-sm); color: var(--color-text-secondary); margin-bottom: var(--space-md); }

.voice-btn {
  width: 100%; padding: 16px; display: flex; align-items: center; justify-content: center; gap: 8px;
  background: var(--color-primary); color: white; border-radius: var(--radius-lg);
  border: none; cursor: pointer; transition: opacity 0.2s;
}
.voice-btn:active { opacity: 0.8; }

.recording-state {
  display: flex; flex-direction: column; align-items: center; gap: var(--space-md);
  padding: var(--space-md); background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-lg); border: 1px solid rgba(239,68,68,0.3);
}
.recording-indicator { display: flex; align-items: center; gap: 8px; font-size: 24px; font-weight: bold; color: var(--color-danger); }
.rec-dot { width: 14px; height: 14px; border-radius: 50%; background: var(--color-danger); }

.recording-actions { display: flex; gap: 10px; width: 100%; }
.stop-btn, .cancel-btn {
  flex: 1; padding: 12px; display: flex; align-items: center; justify-content: center; gap: 6px;
  border-radius: var(--radius-md); border: none; cursor: pointer; font-weight: 500; font-size: 14px;
}
.stop-btn { background: var(--color-success); color: white; }
.cancel-btn { background: var(--color-bg-elevated); color: var(--color-text-secondary); }

.audio-processing {
  display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px;
  color: var(--color-accent); font-weight: bold; font-size: 14px;
}

.povod-form { display: flex; flex-direction: column; gap: var(--space-md); }
.povod-card { display: flex; flex-direction: column; }
.povod-name { font-size: var(--font-size-md); font-weight: 600; margin-bottom: 12px; color: var(--color-text-primary); }

.field label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold; color: var(--color-text-tertiary); margin-bottom: 6px; }
.form-select, .form-input, .form-textarea {
  width: 100%; padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);
  background: var(--color-bg-input); color: var(--color-text-primary); font-size: 14px;
}
.form-select:focus, .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--color-primary); }
.form-select:required:invalid { color: var(--color-text-tertiary); }

.submit-btn {
  width: 100%; padding: 16px; margin-top: 20px; border-radius: var(--radius-xl); border: none;
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px;
}
.submit-btn:disabled { opacity: 0.7; pointer-events: none; }

.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }

.primary-btn { padding: 12px 24px; border-radius: var(--radius-lg); background: var(--color-primary); color: white; border: none; cursor: pointer; font-weight: 500; }

.in-development {
  opacity: 0.6;
  filter: grayscale(100%);
  position: relative;
  pointer-events: none;
}

.dev-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  background: var(--color-bg-elevated);
  color: var(--color-text-tertiary);
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  font-size: 10px;
  font-weight: bold;
  text-transform: uppercase;
  border: 1px solid var(--color-border);
}

.disabled-btn {
  background: var(--color-border) !important;
  color: var(--color-text-secondary) !important;
  cursor: not-allowed;
  opacity: 1 !important;
}

.toast-container { position: fixed; bottom: 80px; left: 0; right: 0; display: flex; justify-content: center; z-index: 100; pointer-events: none; }
.toast { background: var(--color-text-primary); color: var(--color-bg-base); padding: 12px 20px; border-radius: 30px; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
</style>
