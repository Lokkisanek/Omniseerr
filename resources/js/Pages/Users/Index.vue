<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    permissionOptions: { type: Array, default: () => [] },
    defaultPermissions: { type: Number, default: 0 },
});

const page = usePage();
const showCreate = ref(false);
const editingId = ref(null);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    permissions: props.defaultPermissions,
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    permissions: 0,
    is_active: true,
});

const editablePermissions = computed(() =>
    props.permissionOptions.filter((p) => p.value !== 2), // Admin toggled separately for clarity
);

function hasBit(value, bit) {
    return (value & bit) === bit || (value & 2) === 2;
}

function toggleBit(target, bit) {
    if ((target.permissions & 2) === 2 && bit !== 2) {
        return;
    }
    target.permissions = (target.permissions & bit) === bit
        ? target.permissions & ~bit
        : target.permissions | bit;
}

function setAdmin(target, enabled) {
    target.permissions = enabled
        ? target.permissions | 2
        : target.permissions & ~2;
}

function openEdit(user) {
    editingId.value = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.permissions = user.permissions;
    editForm.is_active = user.isActive;
}

function submitCreate() {
    createForm.post('/users', {
        onSuccess: () => {
            createForm.reset();
            createForm.permissions = props.defaultPermissions;
            showCreate.value = false;
        },
    });
}

function submitEdit(userId) {
    editForm.put(`/users/${userId}`, {
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function destroyUser(user) {
    if (!confirm(`Delete ${user.name}?`)) return;
    router.delete(`/users/${user.id}`);
}
</script>

<template>
    <AppLayout title="Users">
        <div class="section">
            <div class="slider-header items-center justify-between">
                <div>
                    <h1 class="heading">Users</h1>
                    <p class="description">Overseerr-style local accounts and permission bitmask.</p>
                </div>
                <button type="button" class="btn btn-primary" @click="showCreate = !showCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Add user
                </button>
            </div>

            <div v-if="showCreate" class="panel mb-6 space-y-4 p-5">
                <h2 class="text-lg font-bold text-white">Create local user</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>Name<input v-model="createForm.name" class="mt-1" /></label>
                    <label>Email<input v-model="createForm.email" type="email" class="mt-1" /></label>
                    <label>Password<input v-model="createForm.password" type="password" class="mt-1" /></label>
                    <label>Confirm<input v-model="createForm.password_confirmation" type="password" class="mt-1" /></label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="mb-0 flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" :checked="hasBit(createForm.permissions, 2)" @change="setAdmin(createForm, $event.target.checked)" />
                        Admin
                    </label>
                    <label
                        v-for="opt in editablePermissions"
                        :key="opt.value"
                        class="mb-0 flex items-center gap-2 text-sm text-gray-300"
                    >
                        <input
                            type="checkbox"
                            :checked="hasBit(createForm.permissions, opt.value)"
                            :disabled="hasBit(createForm.permissions, 2)"
                            @change="toggleBit(createForm, opt.value)"
                        />
                        {{ opt.label }}
                    </label>
                </div>
                <button type="button" class="btn btn-primary" :disabled="createForm.processing" @click="submitCreate">
                    Create user
                </button>
            </div>

            <div class="space-y-3">
                <div v-for="user in users" :key="user.id" class="panel p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="font-bold text-white">
                                {{ user.name }}
                                <span v-if="user.isAdmin" class="ml-2 rounded-full bg-indigo-600/80 px-2 py-0.5 text-[10px] uppercase">Admin</span>
                                <span v-if="!user.isActive" class="ml-2 rounded-full bg-red-600/80 px-2 py-0.5 text-[10px] uppercase">Disabled</span>
                            </div>
                            <div class="text-sm text-gray-400">{{ user.email }} · {{ user.userType }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-default btn-sm" @click="openEdit(user)">Edit</button>
                            <button
                                v-if="page.props.auth.user.id !== user.id"
                                type="button"
                                class="btn btn-danger btn-sm"
                                @click="destroyUser(user)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="editingId === user.id" class="mt-4 space-y-4 border-t border-gray-700 pt-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label>Name<input v-model="editForm.name" class="mt-1" /></label>
                            <label>Email<input v-model="editForm.email" type="email" class="mt-1" /></label>
                            <label>New password<input v-model="editForm.password" type="password" class="mt-1" /></label>
                            <label>Confirm<input v-model="editForm.password_confirmation" type="password" class="mt-1" /></label>
                        </div>
                        <label class="mb-0 flex items-center gap-2 text-sm text-gray-300">
                            <input v-model="editForm.is_active" type="checkbox" />
                            Active
                        </label>
                        <div class="flex flex-wrap gap-3">
                            <label class="mb-0 flex items-center gap-2 text-sm text-gray-300">
                                <input type="checkbox" :checked="hasBit(editForm.permissions, 2)" @change="setAdmin(editForm, $event.target.checked)" />
                                Admin
                            </label>
                            <label
                                v-for="opt in editablePermissions"
                                :key="`e-${opt.value}`"
                                class="mb-0 flex items-center gap-2 text-sm text-gray-300"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasBit(editForm.permissions, opt.value)"
                                    :disabled="hasBit(editForm.permissions, 2)"
                                    @change="toggleBit(editForm, opt.value)"
                                />
                                {{ opt.label }}
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm" @click="submitEdit(user.id)">Save</button>
                            <button type="button" class="btn btn-default btn-sm" @click="editingId = null">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
