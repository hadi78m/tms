export default{
 success(msg){window.AppAlert?.showSuccess?AppAlert.showSuccess(msg):alert(msg)},
 error(msg){window.AppAlert?.showError?AppAlert.showError(msg):alert(msg)},
 confirmDelete(cb){
  if(window.AppAlert?.confirmDelete){AppAlert.confirmDelete(cb)}
  else if(confirm('Delete?')) cb()
 }
}