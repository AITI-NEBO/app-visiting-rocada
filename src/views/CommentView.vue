<template>
  <div class="comment-page">
    <AppHeader title="Комментарий" :showBack="true" />

    <div class="page-content">
      <div class="comment-container">
        <!-- Deal info -->
        <div class="deal-info animate-fade-in-up">
          <span class="material-symbols-rounded deal-info-icon">assignment</span>
          <div class="deal-info-text">
            <span class="deal-info-label">Визит</span>
            <span class="deal-info-title">{{ visit?.title || `Сделка #${$route.params.id}` }}</span>
          </div>
        </div>

        <!-- Input -->
        <div class="input-section animate-fade-in-up" style="animation-delay: 100ms">
          <h3 class="section-title">
            <span class="material-symbols-rounded section-icon">edit_note</span>
            Новый комментарий
          </h3>
          <div class="input-wrap" :class="{ focused: isFocused }">
            <textarea
              v-model="commentText"
              placeholder="Комментарий в таймлайн сделки"
              rows="4"
              @focus="isFocused = true"
              @blur="isFocused = false"
              :disabled="isSent"
            ></textarea>
            <div class="input-footer">
              <span class="char-count" :class="{ warn: commentText.length > 400 }">
                {{ commentText.length }}/500
              </span>
            </div>
          </div>
        </div>

        <!-- Error -->
        <div v-if="errorMsg" class="error-banner">
          <span class="material-symbols-rounded">error</span>
          <span>{{ errorMsg }}</span>
        </div>

        <!-- Success -->
        <div v-if="isSent" class="success-msg animate-scale-in">
          <span class="material-symbols-rounded success-check">check_circle</span>
          <span class="success-text">Комментарий добавлен в таймлайн сделки!</span>
        </div>

        <!-- Action -->
        <div class="action-section animate-fade-in-up" style="animation-delay: 200ms">
          <button
            v-if="!isSent"
            class="send-btn"
            :disabled="!commentText.trim() || isSending"
            @click="sendComment"
          >
            <LogoSpinner v-if="isSending" size="small" />
            <span v-else class="material-symbols-rounded send-icon">send</span>
            {{ isSending ? 'Отправка...' : 'Отправить' }}
          </button>
          <router-link
            v-else
            :to="`/visits/${$route.params.id}`"
            class="send-btn back-btn"
          >
            <span class="material-symbols-rounded send-icon">arrow_back</span>
            Назад к визиту
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import LogoSpinner from '../components/LogoSpinner.vue'
import { useVisits } from '../composables/useVisits'

const route = useRoute()
const { findVisit, loadVisits, addComment } = useVisits()

const visit = ref(null)

onMounted(async () => {
  // Название визита — необязательное украшение; ошибки загрузки не должны ломать страницу
  try {
    await loadVisits()
    visit.value = findVisit(route.params.id)
  } catch (e) {
    console.warn('[CommentView] Could not load visit info:', e)
  }
})

const commentText = ref('')
const isFocused = ref(false)
const isSending = ref(false)
const isSent = ref(false)
const errorMsg = ref('')

async function sendComment() {
  if (!commentText.value.trim()) return
  isSending.value = true
  errorMsg.value = ''
  try {
    await addComment(route.params.id, commentText.value.trim())
    isSent.value = true
  } catch (e) {
    console.error('[CommentView] Error sending comment:', e)
    errorMsg.value = 'Не удалось отправить комментарий: ' + e.message
  } finally {
    isSending.value = false
  }
}
</script>

<style scoped>
.comment-page {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

.page-content {
  flex: 1;
  padding: var(--space-base);
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl));
}

@media (min-width: 768px) {
  .page-content {
    padding: var(--space-xl);
    padding-bottom: var(--space-xl);
  }
}

.comment-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

/* Deal info */
.deal-info {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
}

.deal-info-icon {
  font-size: 24px;
  color: var(--color-primary);
}

.deal-info-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.deal-info-label {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.deal-info-title {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Section title */
.section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  margin-bottom: var(--space-md);
}

.section-icon {
  font-size: 20px;
  color: var(--color-text-tertiary);
}

/* Input */
.input-wrap {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-bg-card);
  overflow: hidden;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.input-wrap.focused {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-glow-primary);
}

textarea {
  width: 100%;
  padding: var(--space-base);
  background: transparent;
  color: var(--color-text-primary);
  resize: none;
  font-size: var(--font-size-base);
  line-height: var(--line-height-normal);
}

textarea::placeholder {
  color: var(--color-text-tertiary);
}

textarea:disabled {
  opacity: 0.5;
}

.input-footer {
  display: flex;
  justify-content: flex-end;
  padding: var(--space-sm) var(--space-base);
  border-top: 1px solid var(--color-border);
}

.char-count {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.char-count.warn {
  color: var(--color-warning);
}

/* Error */
.error-banner {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-base);
  background: rgba(255, 68, 68, 0.1);
  color: var(--color-danger);
  border-radius: var(--radius-lg);
  font-size: var(--font-size-sm);
}

/* Success */
.success-msg {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-base);
  background: rgba(0, 196, 140, 0.1);
  border-radius: var(--radius-lg);
  border: 1px solid rgba(0, 196, 140, 0.2);
}

.success-check {
  font-size: 24px;
  color: var(--color-success);
}

.success-text {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  color: var(--color-success);
}

/* Send button */
.send-btn {
  width: 100%;
  height: 52px;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: transform var(--transition-fast), opacity var(--transition-fast);
  box-shadow: var(--shadow-glow-accent);
  text-decoration: none;
}

.send-btn:active:not(:disabled) {
  transform: scale(0.97);
}

.send-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.send-icon {
  font-size: 22px;
}

.back-btn {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  box-shadow: none;
  color: var(--color-text-primary);
}
</style>
