const PermissionManager = {

    getUserRoles() {

        return (window.AdminUser?.roles || [])
            .map(r => r.toLowerCase())

    },

    hasRole(role) {

        return this.getUserRoles().includes(role.toLowerCase())

    },

    checkRoles(roles) {

        if (!roles || roles.length === 0) return true

        const userRoles = this.getUserRoles()

        return roles.some(role =>
            userRoles.includes(role.toLowerCase())
        )

    }

}

export default PermissionManager
