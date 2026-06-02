 <template>
 <!-- ═══════════════════════════════════════════════════════ -->
    <!-- STATUS MESSAGES                                        -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <transition :name="transitionName" mode="out-in">
      <div :key="statusKey">

        <!-- OFFLINE -->
        <div v-if="payment.isOffline.value" class="status-box status-box--offline">
          <span class="spinner spinner--sm"></span>
          <div class="status-text">
            <span class="status-text__main">No connection</span>
            <span class="status-text__sub">Waiting to reconnect&hellip;</span>
          </div>
        </div>

        <!-- PROCESSING -->
        <div v-else-if="payment.state.value === 'processing'" class="status-box status-box--processing">
          <span class="spinner spinner--sm"></span>
          <div class="status-text">
            <span class="status-text__main">{{ paymentMessage }}</span>
            <span class="status-text__sub">Don't close this window</span>
          </div>
          <div class="progress-dots" aria-hidden="true">
            <span class="dot" :class="{ 'dot--on': processingStage >= 0 }"></span>
            <span class="dot" :class="{ 'dot--on': processingStage >= 1 }"></span>
            <span class="dot" :class="{ 'dot--on': processingStage >= 2 }"></span>
          </div>
        </div>

        <!-- TIMEOUT -->
        <div v-else-if="payment.state.value === 'timeout'" class="status-box status-box--warning">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
          <div class="status-text">
            <span class="status-text__main">Took too long</span>
            <span class="status-text__sub">Your account was not charged &mdash; tap to retry</span>
          </div>
        </div>

        <!-- ERROR -->
        <div v-else-if="payment.state.value === 'error'" class="status-box status-box--error">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div class="status-text">
            <span class="status-text__main">{{ payment.error.value }}</span>
            <span class="status-text__sub">Tap below to try again</span>
          </div>
        </div>

        <!-- SUCCESS -->
        <div v-else-if="payment.state.value === 'success'" class="status-box status-box--success">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20,6 9,17 4,12"/></svg>
          <span class="status-text__main">Payment confirmed</span>
        </div>

      </div>
    </transition>
</template>
