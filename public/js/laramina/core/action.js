// action.js

export function action(meta = {}, handler = null) {

    // اگر meta تابع باشد (یعنی کاربر اشتباهی فقط یک تابع داده باشد)
    if (typeof meta === 'function') {
        return Object.assign(meta, {
            icon: meta.icon,
            color: meta.color,
            tooltip: meta.tooltip,
            confirm: meta.confirm,
        })
    }

    // در حالت استاندارد: meta + handler
    if (typeof handler === 'function') {
        return Object.assign(handler, meta)
    }

    // حالت object handler: { handler, icon, tooltip, ... }
    if (meta.handler && typeof meta.handler === 'function') {
        return Object.assign(meta.handler, meta)
    }

    console.warn('Invalid action() definition', meta)
    return () => {}
}
