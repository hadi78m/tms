// js/laramina/core/route-manager.js (نسخه بهبودیافته)
export const RouteManager = {
    routes: {},
    
    register(name, url) {
        this.routes[name] = url;
    },
    
    registerFromLaravel(routes) {
        if (routes && typeof routes === 'object') {
            this.routes = { ...this.routes, ...routes };
        }
    },
    
    url(name, params = {}) {
        let url = this.routes[name];
        
        if (!url) {
            console.error(`Route "${name}" not found`);
            // Fallback: تلاش برای ساخت URL از نام route
            const fallback = this.buildFallbackUrl(name, params);
            if (fallback) return fallback;
            
            return '#';
        }
        
        // جایگذاری پارامترها
        Object.entries(params).forEach(([key, value]) => {
            url = url.replace(`{${key}}`, value);
            url = url.replace(`:${key}`, value);
        });
        
        return url;
    },
    
    buildFallbackUrl(name, params) {
        // تبدیل sms.credentials.toggle-status به /api/sms/credentials/{id}/toggle-status
        const parts = name.split('.');
        if (parts.length >= 2) {
            let path = '/' + parts.join('/');
            Object.entries(params).forEach(([key, value]) => {
                path = path.replace(`{${key}}`, value);
            });
            return path;
        }
        return null;
    }
};