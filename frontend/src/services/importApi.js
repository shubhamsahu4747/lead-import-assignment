import axios from 'axios';

// Base API configuration
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Accept': 'application/json',
  },
  timeout: 60000,
});

/**
 * Handle API error responses uniformly.
 */
function handleApiError(error) {
  if (error.response) {
    const { status, data } = error.response;

    if (status === 413) {
      throw new Error('CSV file exceeds server upload size limit.');
    }

    if (status === 422) {
      if (data.errors) {
        const errorMessages = Object.values(data.errors).flat().join(' ');
        throw new Error(errorMessages || data.message || 'Validation failed.');
      }
      throw new Error(data.message || 'Validation failed.');
    }

    if (status === 404) {
      throw new Error(data.message || 'Requested resource not found.');
    }

    if (status === 500) {
      throw new Error(data.message || 'Internal server error during import processing.');
    }

    if (status === 503) {
      throw new Error('Service is temporarily unavailable. Please retry shortly.');
    }

    throw new Error(data.message || `Request failed with status code ${status}`);
  }

  if (error.request) {
    throw new Error('Network error: Unable to connect to backend server. Ensure Laravel is running on http://localhost:8000.');
  }

  throw error;
}

export const importApi = {
  /**
   * Upload CSV file with optional notification email and real-time upload progress.
   */
  async uploadCsv(file, notificationEmail = '', onUploadProgress = null) {
    try {
      const formData = new FormData();
      formData.append('file', file);
      if (notificationEmail && notificationEmail.trim() !== '') {
        formData.append('notification_email', notificationEmail.trim());
      }

      const response = await apiClient.post('/imports', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress,
      });

      return response.data;
    } catch (error) {
      handleApiError(error);
    }
  },

  /**
   * Get real-time status of an import.
   */
  async getImportStatus(id) {
    try {
      const response = await apiClient.get(`/imports/${id}`);
      return response.data;
    } catch (error) {
      handleApiError(error);
    }
  },

  /**
   * List previous imports with pagination.
   */
  async getImports(page = 1, perPage = 10) {
    try {
      const response = await apiClient.get('/imports', {
        params: { page, per_page: perPage },
      });
      return response.data;
    } catch (error) {
      handleApiError(error);
    }
  },

  /**
   * Get paginated failed records for an import.
   */
  async getImportFailures(id, page = 1, perPage = 20) {
    try {
      const response = await apiClient.get(`/imports/${id}/failures`, {
        params: { page, per_page: perPage },
      });
      return response.data;
    } catch (error) {
      handleApiError(error);
    }
  },

  /**
   * Get paginated stored leads with optional search filter.
   */
  async getLeads(page = 1, search = '', perPage = 15) {
    try {
      const response = await apiClient.get('/leads', {
        params: { page, search, per_page: perPage },
      });
      return response.data;
    } catch (error) {
      handleApiError(error);
    }
  },

  /**
   * Get direct download URL for failed_records.csv
   */
  getFailedCsvDownloadUrl(id) {
    const base = import.meta.env.VITE_API_BASE_URL || '/api';
    return `${base}/imports/${id}/failed-csv`;
  },

  /**
   * Download a generated sample CSV for testing.
   */
  getSampleCsvUrl(count = 100) {
    const base = import.meta.env.VITE_API_BASE_URL || '/api';
    return `${base}/sample-csv?count=${count}`;
  },
};
