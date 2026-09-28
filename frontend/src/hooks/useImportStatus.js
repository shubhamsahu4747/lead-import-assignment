import { useState, useEffect, useRef, useCallback } from 'react';
import { importApi } from '../services/importApi';

export function useImportStatus(importId, pollingInterval = 2500, onComplete = null) {
  const [importData, setImportData] = useState(null);
  const [loading, setLoading] = useState(Boolean(importId));
  const [error, setError] = useState(null);
  const [isPolling, setIsPolling] = useState(Boolean(importId));
  const timerRef = useRef(null);

  const fetchStatus = useCallback(async () => {
    if (!importId) return;

    try {
      const data = await importApi.getImportStatus(importId);
      setImportData(data);
      setError(null);

      // Check if finished
      if (data.status === 'completed' || data.status === 'failed') {
        setIsPolling(false);
        if (onComplete) {
          onComplete(data);
        }
      }
    } catch (err) {
      setError(err.message || 'Failed to poll import status.');
    } finally {
      setLoading(false);
    }
  }, [importId, onComplete]);

  useEffect(() => {
    if (!importId) {
      setImportData(null);
      setIsPolling(false);
      return;
    }

    setLoading(true);
    setIsPolling(true);
    fetchStatus();

    timerRef.current = setInterval(() => {
      fetchStatus();
    }, pollingInterval);

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, [importId, pollingInterval, fetchStatus]);

  // Stop polling if status is terminal
  useEffect(() => {
    if (!isPolling && timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }
  }, [isPolling]);

  return {
    importData,
    loading,
    error,
    isPolling,
    refetch: fetchStatus,
  };
}
