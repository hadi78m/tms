// js/laramina/core/cache-manager.js
export class CacheManager {
    constructor() {
        this.store = new Map();
        this.ttl = 5 * 60 * 1000; // 5 دقیقه
    }
    
    // ✅ استفاده از سینتکس متد استاندارد (بدون Fat Arrow)
    set(key, value, ttl) {
        var ttlValue = ttl || this.ttl;
        this.store.set(key, {
            value: value,
            expires: Date.now() + ttlValue
        });
    }
    
    get(key) {
        var item = this.store.get(key);
        if (!item) return null;
        if (Date.now() > item.expires) {
            this.store.delete(key);
            return null;
        }
        return item.value;
    }
    
    clear() {
        this.store.clear();
    }
    
    has(key) {
        return this.store.has(key);
    }
    
    delete(key) {
        return this.store.delete(key);
    }
}