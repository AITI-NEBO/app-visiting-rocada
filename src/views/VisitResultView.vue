<template>
  <div class="result-page">
    <AppHeader title="Итог визита" :showBack="true" />

    <div class="page-content">
      <!-- Mock banner -->
      <div class="mock-banner animate-fade-in-up">
        <span class="material-symbols-rounded" style="font-size: 18px">science</span>
        Это демо-версия. Данные не сохраняются на сервер.
      </div>

      <!-- Step indicator -->
      <div class="steps animate-fade-in-up" style="animation-delay: 50ms">
        <div class="step" :class="{ active: currentStep >= 1, done: currentStep > 1 }">
          <div class="step-dot">{{ currentStep > 1 ? '✓' : '1' }}</div>
          <span class="step-label">Геолокация</span>
        </div>
        <div class="step-line" :class="{ active: currentStep > 1 }"></div>
        <div class="step" :class="{ active: currentStep >= 2, done: currentStep > 2 }">
          <div class="step-dot">{{ currentStep > 2 ? '✓' : '2' }}</div>
          <span class="step-label">Результат</span>
        </div>
        <div class="step-line" :class="{ active: currentStep > 2 }"></div>
        <div class="step" :class="{ active: currentStep >= 3 }">
          <div class="step-dot">3</div>
          <span class="step-label">Готово</span>
        </div>
      </div>

      <!-- Step 1: Geo -->
      <div v-if="currentStep === 1" class="step-content animate-fade-in-up">
        <div class="geo-visual">
          <div class="geo-circle" :class="{ locating: isLocating, done: geoDone }">
            <span class="material-symbols-rounded geo-icon">
              {{ geoDone ? 'check_circle' : isLocating ? 'gps_fixed' : 'my_location' }}
            </span>
          </div>
          <p class="geo-text" v-if="!geoDone && !isLocating">
            Подтвердите своё местоположение, чтобы зафиксировать присутствие на визите
          </p>
          <p class="geo-text" v-else-if="isLocating">
            Определение местоположения...
          </p>
          <div v-else class="geo-success">
            <p class="geo-text geo-ok">Геолокация подтверждена!</p>
            <span class="geo-coords">{{ geoLat }}, {{ geoLng }}</span>
          </div>
        </div>

        <button v-if="!geoDone" class="primary-btn" :disabled="isLocating" @click="confirmGeo">
          <span class="material-symbols-rounded">{{ isLocating ? 'hourglass_top' : 'location_on' }}</span>
          {{ isLocating ? 'Определение...' : 'Подтвердить геолокацию' }}
        </button>
        <button v-else class="primary-btn" @click="currentStep = 2">
          <span class="material-symbols-rounded">arrow_forward</span>
          Далее
        </button>
      </div>

      <!-- Step 2: Result -->
      <div v-if="currentStep === 2" class="step-content animate-fade-in-up">
        <h3 class="step-title">Выберите итог визита</h3>

        <div class="result-options">
          <button
            v-for="opt in resultStatusOptions"
            :key="opt.value"
            class="result-option"
            :class="{ selected: selectedResult === opt.value }"
            @click="selectedResult = opt.value"
          >
            <span class="material-symbols-rounded result-option-icon" :style="{ color: opt.color }">{{ opt.icon }}</span>
            <span class="result-option-label">{{ opt.label }}</span>
            <span v-if="selectedResult === opt.value" class="material-symbols-rounded result-check">check_circle</span>
          </button>
        </div>

        <!-- Note -->
        <div class="note-section">
          <h4 class="note-title">
            <span class="material-symbols-rounded" style="font-size: 18px">edit_note</span>
            Комментарий
          </h4>
          <textarea
            v-model="resultText"
            class="note-textarea"
            rows="3"
            placeholder="Опишите итог визита..."
          ></textarea>
        </div>

        <!-- Voice -->
        <div class="voice-section">
          <h4 class="note-title">
            <span class="material-symbols-rounded" style="font-size: 18px">mic</span>
            Голосовая заметка
          </h4>
          <div v-if="!isRecording && !voiceRecorded" class="voice-idle">
            <button class="voice-btn" @click="startRecording">
              <span class="material-symbols-rounded">mic</span>
              Записать голос
            </button>
          </div>
          <div v-else-if="isRecording" class="voice-recording">
            <div class="recording-indicator">
              <span class="rec-dot"></span>
              <span class="rec-time">{{ recordingTime }}с</span>
            </div>
            <button class="voice-btn voice-btn-stop" @click="stopRecording">
              <span class="material-symbols-rounded">stop</span>
              Остановить
            </button>
          </div>
          <div v-else class="voice-done">
            <div class="voice-chip">
              <span class="material-symbols-rounded" style="color: var(--color-accent)">graphic_eq</span>
              <span>Запись {{ recordingTime }}с</span>
              <button class="voice-remove" @click="voiceRecorded = false; recordingTime = 0">
                <span class="material-symbols-rounded">close</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Photo -->
        <div class="photo-section">
          <h4 class="note-title">
            <span class="material-symbols-rounded" style="font-size: 18px">photo_camera</span>
            Фото
          </h4>
          <div class="photo-grid">
            <div v-for="(photo, i) in photos" :key="i" class="photo-thumb">
              <img :src="photo" alt="Фото" />
              <button class="photo-remove" @click="photos.splice(i, 1)">
                <span class="material-symbols-rounded">close</span>
              </button>
            </div>
            <label class="photo-add">
              <span class="material-symbols-rounded">add_a_photo</span>
              <span>Добавить</span>
              <input type="file" accept="image/*" capture="environment" @change="handlePhoto" hidden />
            </label>
          </div>
        </div>

        <button
          class="primary-btn"
          :disabled="!selectedResult"
          @click="submitResult"
        >
          <span class="material-symbols-rounded">check</span>
          Сохранить итог
        </button>
      </div>

      <!-- Step 3: Done -->
      <div v-if="currentStep === 3" class="step-content animate-fade-in-up" style="text-align: center">
        <div class="done-visual">
          <span class="material-symbols-rounded done-icon">verified</span>
          <h3 class="done-title">Визит завершён!</h3>
          <p class="done-text">Итог визита сохранён. Данные отправлены менеджеру.</p>
        </div>
        <router-link :to="`/visits/${$route.params.id}`" class="primary-btn" style="margin-top: var(--space-lg)">
          <span class="material-symbols-rounded">arrow_back</span>
          Вернуться к визиту
        </router-link>
        <router-link to="/" class="secondary-btn" style="margin-top: var(--space-md)">
          <span class="material-symbols-rounded">home</span>
          На главную
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import { resultStatusOptions } from '../data/mock'

const currentStep = ref(1)
const isLocating = ref(false)
const geoDone = ref(false)
const geoLat = ref('55.7887')
const geoLng = ref('49.1221')

const selectedResult = ref(null)
const resultText = ref('')
const isRecording = ref(false)
const voiceRecorded = ref(false)
const recordingTime = ref(0)
const photos = ref([])

let recordingInterval = null

function confirmGeo() {
  isLocating.value = true
  // Try real geolocation
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        geoLat.value = pos.coords.latitude.toFixed(4)
        geoLng.value = pos.coords.longitude.toFixed(4)
        isLocating.value = false
        geoDone.value = true
      },
      () => {
        // Fallback to mock coords
        setTimeout(() => {
          isLocating.value = false
          geoDone.value = true
        }, 1500)
      },
      { timeout: 5000 }
    )
  } else {
    setTimeout(() => {
      isLocating.value = false
      geoDone.value = true
    }, 1500)
  }
}

function startRecording() {
  isRecording.value = true
  recordingTime.value = 0
  recordingInterval = setInterval(() => {
    recordingTime.value++
  }, 1000)
}

function stopRecording() {
  isRecording.value = false
  voiceRecorded.value = true
  clearInterval(recordingInterval)
}

function handlePhoto(e) {
  const file = e.target.files[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (ev) => {
      photos.value.push(ev.target.result)
    }
    reader.readAsDataURL(file)
  }
  e.target.value = ''
}

function submitResult() {
  currentStep.value = 3
}
</script>

<style scoped>
.result-page { display: flex; flex-direction: column; min-height: 100dvh; }

.page-content {
  flex: 1; padding: var(--space-base);
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl));
  display: flex; flex-direction: column; gap: var(--space-lg);
}

@media (min-width: 768px) {
  .page-content { padding: var(--space-xl); padding-bottom: var(--space-xl); }
}

/* Mock banner */
.mock-banner {
  display: flex; align-items: center; gap: var(--space-sm);
  padding: var(--space-md) var(--space-base);
  background: rgba(255, 176, 32, 0.1); color: var(--color-warning);
  border-radius: var(--radius-md); border: 1px solid rgba(255, 176, 32, 0.2);
  font-size: var(--font-size-xs); font-weight: var(--font-weight-medium);
}

/* Steps indicator */
.steps {
  display: flex; align-items: center; justify-content: center;
  gap: 0; padding: var(--space-base) 0;
}

.step { display: flex; flex-direction: column; align-items: center; gap: 6px; }

.step-dot {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--color-bg-elevated); display: flex;
  align-items: center; justify-content: center;
  font-size: var(--font-size-sm); font-weight: var(--font-weight-bold);
  color: var(--color-text-tertiary); transition: all var(--transition-base);
}

.step.active .step-dot {
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
}

.step.done .step-dot { background: var(--color-success); color: white; }

.step-label { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }
.step.active .step-label { color: var(--color-text-primary); font-weight: var(--font-weight-medium); }

.step-line {
  width: 40px; height: 2px; background: var(--color-bg-elevated);
  margin: 0 var(--space-sm); margin-bottom: 20px;
  transition: background var(--transition-base);
}

.step-line.active { background: var(--color-accent); }

/* Step content */
.step-content { display: flex; flex-direction: column; gap: var(--space-lg); }

/* Geo visual */
.geo-visual {
  display: flex; flex-direction: column; align-items: center;
  gap: var(--space-base); padding: var(--space-xl) 0;
}

.geo-circle {
  width: 100px; height: 100px; border-radius: 50%;
  background: var(--color-bg-card); border: 3px solid var(--color-border);
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition-slow); box-shadow: var(--shadow-md);
}

.geo-circle.locating {
  border-color: var(--color-primary);
  animation: pulse 1.5s ease-in-out infinite;
}

.geo-circle.done {
  border-color: var(--color-success);
  background: rgba(0, 196, 140, 0.1);
}

.geo-icon { font-size: 40px; color: var(--color-text-tertiary); transition: color var(--transition-base); }
.geo-circle.locating .geo-icon { color: var(--color-primary); }
.geo-circle.done .geo-icon { color: var(--color-success); }

.geo-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); text-align: center; max-width: 280px; }
.geo-ok { color: var(--color-success); font-weight: var(--font-weight-semibold); }

.geo-success { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.geo-coords { font-size: var(--font-size-xs); color: var(--color-text-tertiary); font-variant-numeric: tabular-nums; }

/* Buttons */
.primary-btn {
  width: 100%; height: 52px; border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold);
  display: flex; align-items: center; justify-content: center; gap: var(--space-sm);
  transition: transform var(--transition-fast), opacity var(--transition-fast);
  box-shadow: var(--shadow-glow-accent); text-decoration: none; border: none; cursor: pointer;
}

.primary-btn:active:not(:disabled) { transform: scale(0.97); }
.primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.secondary-btn {
  width: 100%; height: 48px; border-radius: var(--radius-lg);
  background: var(--color-bg-card); color: var(--color-text-primary);
  font-size: var(--font-size-base); font-weight: var(--font-weight-semibold);
  display: flex; align-items: center; justify-content: center; gap: var(--space-sm);
  border: 1px solid var(--color-border); text-decoration: none;
  transition: transform var(--transition-fast);
}

.secondary-btn:active { transform: scale(0.97); }

/* Result options */
.step-title { font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); }

.result-options { display: flex; flex-direction: column; gap: var(--space-sm); }

.result-option {
  display: flex; align-items: center; gap: var(--space-md);
  padding: var(--space-base); background: var(--color-bg-card);
  border-radius: var(--radius-lg); border: 2px solid var(--color-border);
  cursor: pointer; transition: all var(--transition-fast);
}

.result-option.selected { border-color: var(--color-accent); background: rgba(0, 212, 170, 0.05); }

.result-option-icon { font-size: 24px; flex-shrink: 0; }
.result-option-label { flex: 1; font-size: var(--font-size-base); font-weight: var(--font-weight-medium); }
.result-check { font-size: 22px; color: var(--color-accent); }

/* Note */
.note-title {
  display: flex; align-items: center; gap: var(--space-sm);
  font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold);
  margin-bottom: var(--space-sm);
}

.note-textarea {
  width: 100%; padding: var(--space-base); border-radius: var(--radius-md);
  background: var(--color-bg-input); border: 1px solid var(--color-border);
  color: var(--color-text-primary); font-size: var(--font-size-base);
  line-height: var(--line-height-normal); resize: vertical;
  font-family: var(--font-family);
  transition: border-color var(--transition-fast);
}

.note-textarea:focus { border-color: var(--color-primary); }
.note-textarea::placeholder { color: var(--color-text-tertiary); }

/* Voice */
.voice-btn {
  display: flex; align-items: center; gap: var(--space-sm);
  padding: var(--space-md) var(--space-base); border-radius: var(--radius-md);
  background: var(--color-bg-card); border: 1px solid var(--color-border);
  font-size: var(--font-size-sm); font-weight: var(--font-weight-medium);
  color: var(--color-text-primary); cursor: pointer;
  transition: background var(--transition-fast);
}

.voice-btn:active { background: var(--color-bg-card-hover); }

.voice-btn-stop { border-color: var(--color-danger); color: var(--color-danger); }

.voice-recording { display: flex; align-items: center; gap: var(--space-base); }

.recording-indicator {
  display: flex; align-items: center; gap: var(--space-sm);
  font-size: var(--font-size-sm); color: var(--color-danger);
  font-weight: var(--font-weight-semibold);
}

.rec-dot {
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--color-danger); animation: pulse 1s ease-in-out infinite;
}

.voice-chip {
  display: inline-flex; align-items: center; gap: var(--space-sm);
  padding: var(--space-sm) var(--space-base); border-radius: var(--radius-full);
  background: rgba(0, 212, 170, 0.1); border: 1px solid rgba(0, 212, 170, 0.2);
  font-size: var(--font-size-sm); color: var(--color-text-primary);
}

.voice-remove {
  display: flex; align-items: center; padding: 2px;
  border-radius: 50%; cursor: pointer; color: var(--color-text-tertiary);
}

.voice-remove .material-symbols-rounded { font-size: 16px; }

/* Photo */
.photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-sm); }

.photo-thumb {
  position: relative; aspect-ratio: 1; border-radius: var(--radius-md);
  overflow: hidden; border: 1px solid var(--color-border);
}

.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }

.photo-remove {
  position: absolute; top: 4px; right: 4px;
  width: 24px; height: 24px; border-radius: 50%;
  background: rgba(0,0,0,0.6); color: white;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
}

.photo-remove .material-symbols-rounded { font-size: 16px; }

.photo-add {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 4px; aspect-ratio: 1; border-radius: var(--radius-md);
  border: 2px dashed var(--color-border); cursor: pointer;
  color: var(--color-text-tertiary); font-size: var(--font-size-xs);
  transition: border-color var(--transition-fast);
}

.photo-add:active { border-color: var(--color-primary); }
.photo-add .material-symbols-rounded { font-size: 28px; }

/* Done */
.done-visual { display: flex; flex-direction: column; align-items: center; gap: var(--space-md); padding: var(--space-xl) 0; }
.done-icon { font-size: 64px; color: var(--color-success); animation: successBounce 0.6s ease; }
.done-title { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); }
.done-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); }
</style>
