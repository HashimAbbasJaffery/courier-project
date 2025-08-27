import { ref } from 'vue';

// Global loader state
const isLoading = ref(false);
const loadingMessage = ref('');
const loadingProgress = ref(0);
const loadingType = ref('full'); // 'full' or 'inline'

// Loader queue for multiple operations
const loaderQueue = ref([]);
let queueTimeout = null;

// Show the main loader
const showLoader = (message = 'Loading amazing things for you...', type = 'full') => {
  loadingMessage.value = message;
  loadingType.value = type;
  isLoading.value = true;
  loadingProgress.value = 0;
  
  // Start progress animation
  startProgressAnimation();
  
  return {
    updateProgress: (progress) => {
      loadingProgress.value = Math.min(100, Math.max(0, progress));
    },
    updateMessage: (message) => {
      loadingMessage.value = message;
    },
    hide: () => hideLoader()
  };
};

// Hide the main loader
const hideLoader = () => {
  isLoading.value = false;
  loadingProgress.value = 0;
  loadingMessage.value = '';
  
  // Clear progress animation
  if (queueTimeout) {
    clearTimeout(queueTimeout);
    queueTimeout = null;
  }
};

// Start progress animation
const startProgressAnimation = () => {
  let progress = 0;
  const animate = () => {
    if (progress < 90 && isLoading.value) {
      progress += Math.random() * 15;
      loadingProgress.value = Math.min(90, progress);
      queueTimeout = setTimeout(animate, 200 + Math.random() * 300);
    }
  };
  animate();
};

// Queue-based loader for multiple operations
const queueLoader = (operationId, message = '') => {
  const operation = {
    id: operationId,
    message,
    startTime: Date.now()
  };
  
  loaderQueue.value.push(operation);
  
  return {
    complete: () => completeOperation(operationId),
    updateMessage: (newMessage) => updateOperationMessage(operationId, newMessage)
  };
};

// Complete an operation
const completeOperation = (operationId) => {
  const index = loaderQueue.value.findIndex(op => op.id === operationId);
  if (index > -1) {
    loaderQueue.value.splice(index, 1);
  }
};

// Update operation message
const updateOperationMessage = (operationId, message) => {
  const operation = loaderQueue.value.find(op => op.id === operationId);
  if (operation) {
    operation.message = message;
  }
};

// Get queue status
const getQueueStatus = () => {
  return {
    count: loaderQueue.value.length,
    operations: loaderQueue.value,
    estimatedTime: loaderQueue.value.length * 2000 // Rough estimate
  };
};

// Simulate network delay for testing
const simulateNetworkDelay = (delay = 2000) => {
  return new Promise(resolve => {
    const loader = showLoader('Simulating network delay...');
    let progress = 0;
    
    const interval = setInterval(() => {
      progress += 100 / (delay / 100);
      loader.updateProgress(progress);
      
      if (progress >= 100) {
        clearInterval(interval);
        loader.hide();
        resolve();
      }
    }, 100);
  });
};

// Export the service
export const loaderService = {
  // State
  isLoading,
  loadingMessage,
  loadingProgress,
  loadingType,
  loaderQueue,
  
  // Methods
  showLoader,
  hideLoader,
  queueLoader,
  getQueueStatus,
  simulateNetworkDelay
};

// Make it globally available
if (typeof window !== 'undefined') {
  window.loaderService = loaderService;
}




