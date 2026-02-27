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
            <span class="deal-info-title">{{ visit?.title }}</span>
          </div>
        </div>

        <!-- Existing comments -->
        <div v-if="visit?.comments.length" class="existing-comments animate-fade-in-up" style="animation-delay: 100ms">
          <h3 class="section-title">
            <span class="material-symbols-rounded section-icon">forum</span>
            Комментарии ({{ visit.comments.length }})
          </h3>
          <div class="comments-list">
            <div v-for="(comment, i) in visit.comments" :key="i" class="comment-bubble">
              <div class="bubble-header">
                <div class="bubble-avatar">{{ comment.author[0] }}</div>
                <span class="bubble-author">{{ comment.author }}</span>
                <span class="bubble-time">{{ formatTime(comment.date) }}</span>
              </div>
              <p class="bubble-text">{{ comment.text }}</p>
            </div>
          </div>
        </div>

        <!-- Input -->
        <div class="input-section animate-fade-in-up" style="animation-delay: 200ms">
          <h3 class="section-title">
            <span class="material-symbols-rounded section-icon">edit_note</span>
            Новый комментарий
          </h3>
          <div class="input-wrap" :class="{ focused: isFocused }">
            <textarea
              v-model="commentText"
              placeholder="Напишите комментарий..."
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

        <!-- Success -->
        <div v-if="isSent" class="success-msg animate-scale-in">
          <span class="material-symbols-rounded success-check">check_circle</span>
          <span class="success-text">Комментарий добавлен!</span>
        </div>

        <!-- Action -->
        <div class="action-section animate-fade-in-up" style="animation-delay: 300ms">
          <button
            v-if="!isSent"
            class="send-btn"
            :disabled="!commentText.trim() || isSending"
            @click="sendComment"
          >
            <span v-if="isSending" class="btn-spinner"></span>
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
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { visits, visitsTomorrow } from '../data/mock'

const route = useRoute()
const allVisits = [...visits, ...visitsTomorrow]
const visit = computed(() => allVisits.find(v => v.id === Number(route.params.id)))

const commentText = ref('')
const isFocused = ref(false)
const isSending = ref(false)
const isSent = ref(false)

function formatTime(dateStr) {
  return dateStr.split(' ')[1] || dateStr
}

function sendComment() {
  if (!commentText.value.trim()) return
  isSending.value = true
  setTimeout(() => {
    isSending.value = false
    isSent.value = true
  }, 1200)
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

/* Comments */
.comments-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.comment-bubble {
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
}

.bubble-header {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin-bottom: var(--space-sm);
}

.bubble-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
  color: white;
}

.bubble-author {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  flex: 1;
}

.bubble-time {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.bubble-text {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  line-height: var(--line-height-normal);
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

.btn-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.back-btn {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  box-shadow: none;
  color: var(--color-text-primary);
}
</style>
